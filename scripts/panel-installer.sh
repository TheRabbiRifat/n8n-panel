#!/bin/bash
set -Eeuo pipefail

echo "Starting n8n Panel Installer..."

# Check if running as root
if [ "$(id -u)" -ne 0 ]; then
    echo "❌ Error: This script must be run as root."
    exit 1
fi

#################################
# OS DETECTION
#################################
if [ -f /etc/os-release ]; then
    . /etc/os-release
    OS=$ID
    OS_LIKE=${ID_LIKE:-""}
    VERSION_ID=${VERSION_ID:-""}
else
    echo "❌ Error: Cannot detect OS from /etc/os-release."
    exit 1
fi

is_debian_based() {
    [[ "$OS" == "ubuntu" || "$OS" == "debian" || "$OS_LIKE" == *"debian"* ]]
}

is_rhel_based() {
    [[ "$OS" == "almalinux" || "$OS" == "centos" || "$OS" == "rocky" || "$OS_LIKE" == *"rhel"* || "$OS_LIKE" == *"fedora"* ]]
}

if is_debian_based; then
    WEB_USER="www-data"
    PHP_SOCK="/run/php/php8.2-fpm.sock"
    PHP_SERVICE="php8.2-fpm"
    CRON_SERVICE="cron"
    PG_HBA_PATTERN="/etc/postgresql/*/main/pg_hba.conf"
    PG_CONF_PATTERN="/etc/postgresql/*/main/postgresql.conf"
elif is_rhel_based; then
    WEB_USER="nginx"
    PHP_SOCK="/run/php-fpm/www.sock"
    PHP_SERVICE="php-fpm"
    CRON_SERVICE="crond"
    PG_HBA_PATTERN="/var/lib/pgsql/data/pg_hba.conf"
    PG_CONF_PATTERN="/var/lib/pgsql/data/postgresql.conf"
else
    echo "❌ Error: Unsupported OS ($OS). Only Debian/Ubuntu and RHEL-based OS (AlmaLinux, CentOS, Rocky) are supported."
    exit 1
fi

#################################
# CONFIGURATION
#################################
APP_DIR="/var/n8n-panel"
TMP_DIR="/tmp/n8n-panel-install"
ZIP_URL="https://github.com/TheRabbiRifat/n8n-panel/archive/refs/heads/main.zip"

NGINX_PANEL_DIR="/var/lib/n8n/nginx"
INSTANCES_DIR="/var/lib/n8n/instances"

PANEL_PORT=8448

HOSTNAME_FQDN="$(hostname -f 2>/dev/null || hostname 2>/dev/null || echo 'localhost')"
EMAIL="admin@${HOSTNAME_FQDN}"

DB_NAME="n8n_panel"
DB_USER="n8n_panel"
set +o pipefail
DB_PASS="$(LC_ALL=C tr -dc a-z1-9 </dev/urandom | head -c 11)"
set -o pipefail

NGINX_MAIN_CONF="/etc/nginx/nginx.conf"
NGINX_INCLUDE_LINE="include /var/lib/n8n/nginx/*.conf;"

CONF_NAME="n8n-panel-${PANEL_PORT}.conf"
CONF_PATH="${NGINX_PANEL_DIR}/${CONF_NAME}"
CONF_BACKUP="${CONF_PATH}.bak"

#################################
# ROLLBACK
#################################
rollback() {
    echo "⚠ Deployment failed — rolling back"
    [ -f "$CONF_BACKUP" ] && mv -f "$CONF_BACKUP" "$CONF_PATH"
    nginx -t && systemctl reload nginx || true
    rm -rf "$TMP_DIR"
    exit 1
}
trap rollback ERR

#################################
# 1. INSTALL SYSTEM PACKAGES
#################################
echo "Installing system packages..."

if is_debian_based; then
    apt update -y
    apt install -y ca-certificates curl gnupg software-properties-common dnsutils

    # Add PHP 8.2 Repository
    echo "Adding PHP Repository..."
    add-apt-repository -y ppa:ondrej/php
    apt update -y
elif is_rhel_based; then
    dnf install -y epel-release dnf-plugins-core
    dnf install -y curl bind-utils gnupg2 ca-certificates
fi

# Get Public IP
PUBLIC_IP=$(curl -s https://api.ipify.org || curl -s https://ifconfig.me)

# Prompt for Hostname
CURRENT_HOSTNAME=$(hostname -f 2>/dev/null || hostname 2>/dev/null || echo 'localhost')
echo "Current hostname is: $CURRENT_HOSTNAME"
read -p "Enter hostname to use (leave blank to use current): " INPUT_HOSTNAME
if [ -n "$INPUT_HOSTNAME" ]; then
    HOSTNAME_FQDN="$INPUT_HOSTNAME"
else
    HOSTNAME_FQDN="$CURRENT_HOSTNAME"
fi

# Update dependent variables
EMAIL="admin@${HOSTNAME_FQDN}"

# DNS Check Loop
while true; do
    echo "Checking DNS records for $HOSTNAME_FQDN..."

    # Check A record for HOSTNAME_FQDN
    A_RECORD=$(dig @1.1.1.1 +short "$HOSTNAME_FQDN" A | head -n 1)

    # Check A record for wildcard
    WILDCARD_RECORD=$(dig @1.1.1.1 +short "*.$HOSTNAME_FQDN" A | head -n 1)

    if [[ "$A_RECORD" == "$PUBLIC_IP" && "$WILDCARD_RECORD" == "$PUBLIC_IP" ]]; then
        echo "✅ DNS records verified!"
        break
    else
        echo "❌ DNS verification failed."
        echo "Expected IP: $PUBLIC_IP"
        echo "Found A Record for $HOSTNAME_FQDN: ${A_RECORD:-None}"
        echo "Found Wildcard Record for *.$HOSTNAME_FQDN: ${WILDCARD_RECORD:-None}"
        echo ""
        echo "Please ensure you have A records for '$HOSTNAME_FQDN' and '*.$HOSTNAME_FQDN' pointing to $PUBLIC_IP"

        read -p "Press Enter to retry check, or type 'skip' to ignore and proceed: " CHOICE
        if [[ "$CHOICE" == "skip" ]]; then
            echo "⚠ Skipping DNS verification. Proceeding..."
            break
        fi
    fi
done

if is_debian_based; then
    # Setup Docker Repo
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor --yes -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    echo \
      "deb [arch=\"$(dpkg --print-architecture)\" signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
      $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
      tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt update -y

    apt install -y \
        nginx \
        docker-ce docker-ce-cli docker-buildx-plugin docker-compose-plugin docker-ce-rootless-extras \
        git \
        postgresql postgresql-contrib \
        php8.2-fpm php8.2-cli php8.2-pgsql php8.2-mbstring php8.2-bcmath \
        php8.2-curl php8.2-xml php8.2-zip php8.2-intl php8.2-gd \
        unzip zip composer cron \
        certbot python3-certbot-nginx ufw
elif is_rhel_based; then
    # Docker Repo
    dnf config-manager --add-repo https://download.docker.com/linux/centos/docker-ce.repo

    # PHP Repo (Remi)
    MAJOR_VERSION=$(echo "$VERSION_ID" | cut -d'.' -f1)
    dnf install -y "https://rpms.remirepo.net/enterprise/remi-release-${MAJOR_VERSION}.rpm" || true
    dnf module reset php -y
    dnf module enable php:remi-8.2 -y

    dnf install -y \
        nginx \
        docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin \
        git \
        postgresql-server postgresql-contrib \
        php-fpm php-cli php-pgsql php-mbstring php-bcmath \
        php-curl php-xml php-zip php-intl php-gd \
        unzip zip composer cronie \
        certbot python3-certbot-nginx firewalld

    # RHEL specific Postgresql initialization
    postgresql-setup --initdb || true

    # Fix php-fpm running as apache instead of nginx for Remi Repo
    sed -i "s/^user = apache/user = nginx/" /etc/php-fpm.d/www.conf || true
    sed -i "s/^group = apache/group = nginx/" /etc/php-fpm.d/www.conf || true
    sed -i "s/^listen.owner = nobody/listen.owner = nginx/" /etc/php-fpm.d/www.conf || true
    sed -i "s/^listen.group = nobody/listen.group = nginx/" /etc/php-fpm.d/www.conf || true

    # Disable SELinux if enforcing
    if command -v setenforce &> /dev/null; then
        setenforce 0 || true
        sed -i 's/^SELINUX=.*/SELINUX=permissive/g' /etc/selinux/config || true
    fi
fi

systemctl enable --now docker
systemctl enable --now $CRON_SERVICE
if is_rhel_based; then
    systemctl enable --now nginx
    systemctl enable --now postgresql
    systemctl enable --now $PHP_SERVICE
fi

#################################
# 1.1 HOSTNAME CONFIGURATION
#################################
PUBLIC_IP=$(curl -s https://api.ipify.org || echo "YOUR_SERVER_IP")
CURRENT_HOSTNAME=$(hostname -f 2>/dev/null || hostname 2>/dev/null || echo 'localhost')

echo ""
echo "----------------------------------------------------------------"
echo "Please enter the hostname for this panel (e.g. panel.example.com)"
echo "Current detected hostname: $CURRENT_HOSTNAME"
echo "Server Public IP: $PUBLIC_IP"
echo "----------------------------------------------------------------"
# If running non-interactively, this read might fail or hang without -t check?
# But installer is usually interactive.
if [ -t 0 ]; then
    read -p "Hostname [Press Enter to use '$CURRENT_HOSTNAME']: " USER_HOSTNAME
else
    USER_HOSTNAME=""
fi

if [[ -z "$USER_HOSTNAME" ]]; then
    USER_HOSTNAME="$CURRENT_HOSTNAME"
fi

HOSTNAME_FQDN="$USER_HOSTNAME"
EMAIL="admin@${HOSTNAME_FQDN}"

# Loop for DNS Verification (Mandatory)
while true; do
    echo "Verifying DNS records for $HOSTNAME_FQDN..."

    A_RECORD=$(dig @1.1.1.1 +short "$HOSTNAME_FQDN" A | head -n1)
    WILDCARD_RECORD=$(dig @1.1.1.1 +short "random-check.$HOSTNAME_FQDN" A | head -n1)

    if [[ -n "$A_RECORD" && -n "$WILDCARD_RECORD" ]]; then
        echo "✅ DNS records found."
        echo "A Record: $A_RECORD"
        echo "Wildcard Test: $WILDCARD_RECORD"
        if [[ "$A_RECORD" != "$PUBLIC_IP" && "$PUBLIC_IP" != "YOUR_SERVER_IP" ]]; then
             echo "⚠️ Warning: DNS IP ($A_RECORD) does not match detected Public IP ($PUBLIC_IP)."
        fi
        break
    else
        echo "❌ DNS records not found or incomplete."
        echo "Please ensure the following A records exist:"
        echo "  $HOSTNAME_FQDN -> $PUBLIC_IP"
        echo "  *.$HOSTNAME_FQDN -> $PUBLIC_IP"
        echo ""
        if [ -t 0 ]; then
            read -p "Press Enter to recheck..." dummy
        else
            echo "Non-interactive mode: DNS check failed. Aborting."
            exit 1
        fi
    fi
    sleep 2
done

#################################
# 2. FIREWALL
#################################
DOCKER_SUBNET=$(docker network inspect bridge --format='{{(index .IPAM.Config 0).Subnet}}' 2>/dev/null || echo "172.17.0.0/16")

if is_debian_based; then
    ufw allow 80/tcp
    ufw allow 443/tcp
    ufw allow ${PANEL_PORT}/tcp
    # SSH
    ufw allow 22/tcp
    # FTP
    ufw allow 21/tcp
    # SMTP
    ufw allow 25/tcp
    ufw allow 465/tcp
    ufw allow 587/tcp
    # PostgreSQL (Restrict to Localhost and Docker Subnet)
    ufw allow from 127.0.0.1 to any port 5432
    ufw allow from "$DOCKER_SUBNET" to any port 5432
    ufw reload || true
elif is_rhel_based; then
    systemctl enable --now firewalld
    firewall-cmd --permanent --add-port=80/tcp
    firewall-cmd --permanent --add-port=443/tcp
    firewall-cmd --permanent --add-port=${PANEL_PORT}/tcp
    # SSH
    firewall-cmd --permanent --add-port=22/tcp
    # FTP
    firewall-cmd --permanent --add-port=21/tcp
    # SMTP
    firewall-cmd --permanent --add-port=25/tcp
    firewall-cmd --permanent --add-port=465/tcp
    firewall-cmd --permanent --add-port=587/tcp
    # PostgreSQL (Restrict to Localhost and Docker Subnet)
    firewall-cmd --permanent --add-rich-rule="rule family='ipv4' source address='127.0.0.1' port port='5432' protocol='tcp' accept"
    firewall-cmd --permanent --add-rich-rule="rule family='ipv4' source address='${DOCKER_SUBNET}' port port='5432' protocol='tcp' accept"
    firewall-cmd --reload || true
fi

#################################
# 3. USERS, GROUPS, PERMISSIONS
#################################
groupadd -f n8n
mkdir -p "$NGINX_PANEL_DIR" "$INSTANCES_DIR"
chown -R root:n8n /var/lib/n8n
chmod 2775 /var/lib/n8n "$NGINX_PANEL_DIR" "$INSTANCES_DIR"
usermod -aG n8n ${WEB_USER}
usermod -aG docker ${WEB_USER}

#################################
# 4. SUDOERS
#################################
SUDO_FILE="/etc/sudoers.d/n8n-panel"
cat > "$SUDO_FILE" <<EOF
${WEB_USER} ALL=(root) NOPASSWD: /var/n8n-panel/scripts/*.sh
EOF
chmod 0440 "$SUDO_FILE"
visudo -cf "$SUDO_FILE"

#################################
# 5. NGINX INCLUDE
#################################
if ! grep -qF "$NGINX_INCLUDE_LINE" "$NGINX_MAIN_CONF"; then
    sed -i "/http {/a\\    ${NGINX_INCLUDE_LINE}" "$NGINX_MAIN_CONF"
fi
nginx -t
systemctl reload nginx

# Download custom 502 and default index pages
mkdir -p /usr/share/nginx/html/errors
bash -c "curl -fsSL https://raw.githubusercontent.com/TheRabbiRifat/n8n-panel/main/default/502.html -o /usr/share/nginx/html/errors/502.html"

mkdir -p /var/www/html
bash -c "curl -fsSL https://raw.githubusercontent.com/TheRabbiRifat/n8n-panel/main/default/index.html -o /var/www/html/index.html"

#################################
# 6. POSTGRESQL CLEAN + SETUP
#################################
echo "Configuring PostgreSQL..."

PG_HBA_FILE=$(ls ${PG_HBA_PATTERN} 2>/dev/null | head -n 1 || true)
if [ -f "$PG_HBA_FILE" ]; then
    # Force scram-sha-256 auth for local connections
    sed -i "s/^local\s\+all\s\+all\s\+peer/local all all scram-sha-256/" "$PG_HBA_FILE"
    # Ensure host connections also use scram-sha-256
    sed -i "s/^host\s\+all\s\+all\s\+127.0.0.1\/32\s\+.*/host    all             all             127.0.0.1\/32            scram-sha-256/" "$PG_HBA_FILE"
    sed -i "s/^host\s\+all\s\+all\s\+::1\/128\s\+.*/host    all             all             ::1\/128                 scram-sha-256/" "$PG_HBA_FILE"
fi

# Listen on all addresses
PG_CONF_FILE=$(ls ${PG_CONF_PATTERN} 2>/dev/null | head -n 1 || true)
if [ -f "$PG_CONF_FILE" ]; then
    if ! grep -q "^listen_addresses = '*'" "$PG_CONF_FILE"; then
        sed -i "s/^#\?listen_addresses =.*/listen_addresses = '*'/" "$PG_CONF_FILE"
    fi
fi

systemctl restart postgresql

# Drop previous DB/user if exist
sudo -u postgres psql -c "DROP DATABASE IF EXISTS ${DB_NAME};"
sudo -u postgres psql -c "DROP ROLE IF EXISTS ${DB_USER};"

# Create DB/user
sudo -u postgres psql -c "CREATE ROLE ${DB_USER} LOGIN PASSWORD '${DB_PASS}';"
sudo -u postgres psql -c "CREATE DATABASE ${DB_NAME} OWNER ${DB_USER};"
sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE ${DB_NAME} TO ${DB_USER};"

#################################
# 7. DOWNLOAD PANEL
#################################
rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"
curl -fsSL "$ZIP_URL" -o "$TMP_DIR/panel.zip"
unzip -q "$TMP_DIR/panel.zip" -d "$TMP_DIR"
set +o pipefail
SRC_DIR="$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -n1)"
set -o pipefail
rm -rf "$APP_DIR"
mv "$SRC_DIR" "$APP_DIR"

# Set ownership for Laravel
chown -R ${WEB_USER}:${WEB_USER} "$APP_DIR"
chmod -R 775 "$APP_DIR"

cd "$APP_DIR"

#################################
# 8. LARAVEL CONFIG
#################################
sudo -u ${WEB_USER} cp .env.example .env

sudo -u ${WEB_USER} sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=pgsql|" .env
sudo -u ${WEB_USER} sed -i "s|^.*DB_HOST=.*|DB_HOST=127.0.0.1|" .env
sudo -u ${WEB_USER} sed -i "s|^.*DB_PORT=.*|DB_PORT=5432|" .env
sudo -u ${WEB_USER} sed -i "s|^.*DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
sudo -u ${WEB_USER} sed -i "s|^.*DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
sudo -u ${WEB_USER} sed -i "s|^.*DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
sudo -u ${WEB_USER} sed -i "s|^APP_URL=.*|APP_URL=https://${HOSTNAME_FQDN}:${PANEL_PORT}|" .env

# Make storage and cache writable
chown -R ${WEB_USER}:${WEB_USER} storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Install dependencies and setup Laravel
sudo -u ${WEB_USER} composer install --no-dev --optimize-autoloader
sudo -u ${WEB_USER} php artisan key:generate --force
sudo -u ${WEB_USER} php artisan migrate --force
sudo -u ${WEB_USER} php artisan db:seed --force
sudo -u ${WEB_USER} php artisan config:clear
sudo -u ${WEB_USER} php artisan config:cache
sudo -u ${WEB_USER} php artisan route:cache
sudo -u ${WEB_USER} php artisan view:cache

systemctl restart ${PHP_SERVICE}

#################################
# 9. SSL (Let's Encrypt)
#################################
SSL_CERT="/etc/letsencrypt/live/${HOSTNAME_FQDN}/fullchain.pem"
SSL_KEY="/etc/letsencrypt/live/${HOSTNAME_FQDN}/privkey.pem"

if [[ ! -f "$SSL_CERT" || ! -f "$SSL_KEY" ]]; then
    certbot certonly --nginx \
        -d "$HOSTNAME_FQDN" \
        --non-interactive \
        --agree-tos \
        -m "$EMAIL"
fi
systemctl enable certbot.timer

#################################
# 10. NGINX PANEL (8448)
#################################
[ -f "$CONF_PATH" ] && cp "$CONF_PATH" "$CONF_BACKUP"

cat > "$CONF_PATH" <<EOF
server {
    listen ${PANEL_PORT} ssl http2;
    server_name ${HOSTNAME_FQDN};

    ssl_certificate     ${SSL_CERT};
    ssl_certificate_key ${SSL_KEY};

    root ${APP_DIR}/public;
    index index.php;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

nginx -t
systemctl reload nginx

#################################
# CLEANUP
#################################
rm -rf "$TMP_DIR"

# Setup Auto-Backup Cron (ensure it exists)
echo "Configuring automatic backups scheduler..."
CRON_JOB="* * * * * cd ${APP_DIR} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"

CRON_FILE=$(mktemp)
# Get existing crontab, ignore error if empty
crontab -u ${WEB_USER} -l > "$CRON_FILE" 2>/dev/null || true

# Append job if not present
if ! grep -Fq "artisan schedule:run" "$CRON_FILE"; then
    echo "$CRON_JOB" >> "$CRON_FILE"
    # Install new crontab from file
    crontab -u ${WEB_USER} "$CRON_FILE"
    echo "Cron job added."
else
    echo "Cron job already exists."
fi

rm -f "$CRON_FILE"

echo "======================================"
echo "✅ n8n Panel installed successfully"
echo "URL: https://${HOSTNAME_FQDN}:${PANEL_PORT}"
echo "PostgreSQL DB: ${DB_NAME}"
echo "DB User: ${DB_USER}"
echo "DB Password: ${DB_PASS}"
echo ""
echo "Admin Login:"
echo "Email: admin@example.com"
echo "Password: password"
echo "======================================"
