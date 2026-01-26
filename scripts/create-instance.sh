#!/bin/bash

# create-instance.sh
# Usage: ./create-instance.sh --id=ID --name=NAME --port=PORT --image=TAG --cpu=CPU --memory=MEM --domain=DOMAIN --email=EMAIL [--env-json=JSON]

set -e

ID=""
NAME=""
PORT=""
IMAGE="latest"
CPU=""
MEMORY=""
DOMAIN=""
EMAIL=""
ENV_JSON="{}"

# DB Args
DB_HOST=""
DB_PORT="5432"
DB_NAME=""
DB_USER=""
DB_PASS=""
PANEL_DB_USER=""

# Parse arguments
for i in "$@"
do
case $i in
    --id=*)
    ID="${i#*=}"
    ;;
    --name=*)
    NAME="${i#*=}"
    ;;
    --port=*)
    PORT="${i#*=}"
    ;;
    --image=*)
    IMAGE="${i#*=}"
    ;;
    --cpu=*)
    CPU="${i#*=}"
    ;;
    --memory=*)
    MEMORY="${i#*=}"
    ;;
    --domain=*)
    DOMAIN="${i#*=}"
    ;;
    --email=*)
    EMAIL="${i#*=}"
    ;;
    --env-json=*)
    ENV_JSON="${i#*=}"
    ;;
    --db-host=*)
    DB_HOST="${i#*=}"
    ;;
    --db-port=*)
    DB_PORT="${i#*=}"
    ;;
    --db-name=*)
    DB_NAME="${i#*=}"
    ;;
    --db-user=*)
    DB_USER="${i#*=}"
    ;;
    --db-pass=*)
    DB_PASS="${i#*=}"
    ;;
    --panel-db-user=*)
    PANEL_DB_USER="${i#*=}"
    ;;
    *)
            # unknown option
    ;;
esac
done

if [ -z "$NAME" ] || [ -z "$PORT" ] || [ -z "$DOMAIN" ]; then
    echo "Error: Missing required arguments."
    echo "Usage: $0 --id=ID --name=NAME --port=PORT --domain=DOMAIN --image=TAG --cpu=CPU --memory=MEM --email=EMAIL"
    exit 1
fi

# ----------------------------------------------------------------
# 0. PostgreSQL Network Setup (Idempotent)
# ----------------------------------------------------------------
# Detect Docker Bridge Gateway and Subnet
DOCKER_GATEWAY=$(docker network inspect bridge --format='{{(index .IPAM.Config 0).Gateway}}')
DOCKER_SUBNET=$(docker network inspect bridge --format='{{(index .IPAM.Config 0).Subnet}}')

# Find config files (dynamically as version might vary)
PG_CONF_FILE=$(find /etc/postgresql -name postgresql.conf 2>/dev/null | head -n 1)
PG_HBA_FILE=$(find /etc/postgresql -name pg_hba.conf 2>/dev/null | head -n 1)

if [ ! -z "$PG_CONF_FILE" ] && [ ! -z "$PG_HBA_FILE" ]; then
    PG_RELOAD_NEEDED=false

    # 1. Listen Addresses -> '*'
    if ! grep -q "^listen_addresses = '*'" "$PG_CONF_FILE"; then
        sed -i "s/^#\?listen_addresses =.*/listen_addresses = '*'/" "$PG_CONF_FILE"
        PG_RELOAD_NEEDED=true
    fi

    # 2. HBA Config -> Allow Docker Subnet
    if ! grep -q "$DOCKER_SUBNET" "$PG_HBA_FILE"; then
        echo "host    all             all             $DOCKER_SUBNET            scram-sha-256" >> "$PG_HBA_FILE"
        PG_RELOAD_NEEDED=true
    fi

    # 3. HBA Config -> Safety check for 127.0.0.1 (Panel Access)
    if ! grep -q "127.0.0.1/32" "$PG_HBA_FILE"; then
         # This should usually exist, but if broken, restore it.
         echo "host    all             all             127.0.0.1/32            scram-sha-256" >> "$PG_HBA_FILE"
         PG_RELOAD_NEEDED=true
    fi

    if [ "$PG_RELOAD_NEEDED" = true ]; then
        echo "Updating PostgreSQL configuration..."
        systemctl restart postgresql
    fi
fi

# ----------------------------------------------------------------
# 0.5 PostgreSQL User & DB Provisioning
# ----------------------------------------------------------------
# Only provision if credentials are provided
if [ ! -z "$DB_NAME" ] && [ ! -z "$DB_USER" ] && [ ! -z "$DB_PASS" ]; then
    # Provision if we found Postgres config (implies Postgres is installed on host)
    if [ ! -z "$PG_CONF_FILE" ]; then
        echo "Provisioning PostgreSQL database ($DB_NAME) and user ($DB_USER)..."

        # Create User if not exists (Quote identifiers to preserve case)
        sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname = '${DB_USER}'" | grep -q 1 || \
        sudo -u postgres psql -c "CREATE USER \"${DB_USER}\" WITH PASSWORD '${DB_PASS}'"

        # Always update password to ensure it matches the container env (and passed arg)
        sudo -u postgres psql -c "ALTER USER \"${DB_USER}\" WITH PASSWORD '${DB_PASS}'"

        # Create Database if not exists
        sudo -u postgres psql -tc "SELECT 1 FROM pg_database WHERE datname = '${DB_NAME}'" | grep -q 1 || \
        sudo -u postgres psql -c "CREATE DATABASE \"${DB_NAME}\" OWNER \"${DB_USER}\""

        # Grant privileges
        sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE \"${DB_NAME}\" TO \"${DB_USER}\""

        # Grant Panel User privileges (for SSO/Admin access)
        if [ ! -z "$PANEL_DB_USER" ]; then
             # Check if panel user exists
             if sudo -u postgres psql -tc "SELECT 1 FROM pg_roles WHERE rolname = '${PANEL_DB_USER}'" | grep -q 1; then
                  echo "Granting ${PANEL_DB_USER} access to ${DB_USER} role..."
                  sudo -u postgres psql -c "GRANT \"${DB_USER}\" TO \"${PANEL_DB_USER}\"" || true
             fi
        fi
    fi
fi

# Use ID for volume path if provided, else fallback to NAME (for backward compatibility or recovery)
if [ ! -z "$ID" ]; then
    VOLUME_HOST_PATH="/var/lib/n8n/instances/${ID}"
else
    # Fallback to name if no ID provided (legacy behavior)
    VOLUME_HOST_PATH="/var/lib/n8n/instances/${NAME}"
fi

FULL_IMAGE="n8nio/n8n:${IMAGE}"

echo "Starting creation for $NAME ($DOMAIN) on port $PORT..."

# 1. Prepare Volume
mkdir -p "$VOLUME_HOST_PATH"
chmod 777 "$VOLUME_HOST_PATH"

# 2. Build Docker Command using Arrays (Safety)
CMD_ARGS=("run" "-d" "--name" "$NAME" "--restart" "unless-stopped" "-p" "${PORT}:5678")

# Resources
if [ ! -z "$CPU" ]; then
    CMD_ARGS+=("--cpus=${CPU}")
fi
if [ ! -z "$MEMORY" ]; then
    # Ensure memory has unit if numeric
    if [[ "$MEMORY" =~ ^[0-9]+(\.[0-9]+)?$ ]]; then
        CMD_ARGS+=("--memory=${MEMORY}g")
    else
        CMD_ARGS+=("--memory=${MEMORY}")
    fi
fi

# Inject Default PostgreSQL Configuration
# Only if DB credentials provided
if [ ! -z "$DB_NAME" ] && [ ! -z "$DB_USER" ] && [ ! -z "$DB_PASS" ]; then
    # Use provided host or fallback to gateway
    USE_HOST="${DB_HOST:-$DOCKER_GATEWAY}"

    CMD_ARGS+=("-e" "DB_TYPE=postgresdb")
    CMD_ARGS+=("-e" "DB_POSTGRESDB_HOST=${USE_HOST}")
    CMD_ARGS+=("-e" "DB_POSTGRESDB_PORT=${DB_PORT}")
    CMD_ARGS+=("-e" "DB_POSTGRESDB_DATABASE=${DB_NAME}")
    CMD_ARGS+=("-e" "DB_POSTGRESDB_USER=${DB_USER}")
    CMD_ARGS+=("-e" "DB_POSTGRESDB_PASSWORD=${DB_PASS}")
fi

# Environment Variables
# Parse JSON using PHP (guaranteed to be present)
# We output KEY=VAL lines and read them
# Handle spaces/special chars by using specific delimiter or careful reading
if [ ! -z "$ENV_JSON" ] && [ "$ENV_JSON" != "{}" ]; then
    # We use php to parse json and output null-delimited key=value pairs
    # But bash loop over nulls is tricky.
    # Let's simple output KEY and VALUE on separate lines

    # Actually, simpler: PHP outputs bash safe export commands? No.
    # We need to append to CMD_ARGS array.

    # Let's use a temporary file to store the parsed environment variables
    ENV_FILE=$(mktemp)

    # PHP script to parse JSON and write KEY\0VALUE\0 to stdout
    php -r '$d=json_decode($argv[1], true); if($d) foreach($d as $k=>$v) echo $k."\0".$v."\0";' "$ENV_JSON" > "$ENV_FILE"

    # Read from file using loop with null delimiter
    while IFS= read -r -d '' KEY && IFS= read -r -d '' VAL; do
        CMD_ARGS+=("-e" "${KEY}=${VAL}")
    done < "$ENV_FILE"

    rm -f "$ENV_FILE"
fi

# Volumes
CMD_ARGS+=("-v" "${VOLUME_HOST_PATH}:/home/node/.n8n")

# Image
CMD_ARGS+=("${FULL_IMAGE}")

# Run Docker
echo "Executing: docker ${CMD_ARGS[*]}"
docker "${CMD_ARGS[@]}"

# 3. Nginx Configuration
# Config location: /var/lib/n8n/nginx/$NAME.conf
NGINX_DIR="/var/lib/n8n/nginx"
mkdir -p "$NGINX_DIR"
NGINX_CONF="${NGINX_DIR}/${NAME}.conf"

cat > "$NGINX_CONF" <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;

    location / {
        proxy_pass http://127.0.0.1:$PORT;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;

        # WebSocket Support
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";

        # Long timeout for n8n
        proxy_read_timeout 3600;
    }

    # Serve custom 502 page
    error_page 502 /errors/502.html;
    location = /errors/502.html {
        root /usr/share/nginx/html;
        internal;
    }
}
EOF

# Enable Site
# No need to link to sites-enabled as /var/lib/n8n/nginx/*.conf is included in main config
# ln -sf "$NGINX_CONF" "/etc/nginx/sites-enabled/$DOMAIN"
systemctl reload nginx

# 4. SSL (Certbot)
# Best effort
if [ ! -z "$EMAIL" ]; then
    echo "Attempting SSL certification..."
    certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos --email "$EMAIL" --redirect || echo "SSL setup failed, but continuing."

    # 5. Enforce TLS 1.2+ (Fix for err_ssl_version_or_cipher_mismatch)
    if [ -f "$NGINX_CONF" ] && grep -q "listen.*443.*ssl" "$NGINX_CONF"; then
        echo "Enforcing TLS 1.2+ configuration..."
        # If ssl_protocols exists, update it; otherwise insert it
        if grep -q "ssl_protocols" "$NGINX_CONF"; then
            sed -i 's/ssl_protocols.*/ssl_protocols TLSv1.2 TLSv1.3;/' "$NGINX_CONF"
        else
            # Insert after the listen 443 line
            sed -i '/listen.*443.*ssl/a \    ssl_protocols TLSv1.2 TLSv1.3;' "$NGINX_CONF"
        fi
        systemctl reload nginx
    fi
fi

echo "Instance $NAME created successfully."
