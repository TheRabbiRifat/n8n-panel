#!/bin/bash
set -Eeuo pipefail

echo "Starting n8n Panel Installer..."

# Check if running as root
if [ "$(id -u)" -ne 0 ]; then
    echo "❌ Error: This script must be run as root."
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
PHP_SOCK="/run/php/php8.2-fpm.sock"

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
apt update -y
apt install -y \
    nginx \
    docker.io \
    git \
    postgresql postgresql-contrib \
    php8.2-fpm php8.2-cli php8.2-pgsql php8.2-mbstring php8.2-bcmath \
    php8.2-curl php8.2-xml php8.2-zip php8.2-intl php8.2-gd \
    unzip curl composer \
    certbot python3-certbot-nginx ufw

systemctl enable --now docker

#################################
# 2. FIREWALL
#################################
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow ${PANEL_PORT}/tcp
ufw reload || true

#################################
# 3. USERS, GROUPS, PERMISSIONS
#################################
groupadd -f n8n
mkdir -p "$NGINX_PANEL_DIR" "$INSTANCES_DIR"
chown -R root:n8n /var/lib/n8n
chmod 2775 /var/lib/n8n "$NGINX_PANEL_DIR" "$INSTANCES_DIR"
usermod -aG n8n www-data
usermod -aG docker www-data

#################################
# 4. SUDOERS
#################################
SUDO_FILE="/etc/sudoers.d/n8n-panel"
cat > "$SUDO_FILE" <<EOF
www-data ALL=(root) NOPASSWD: /var/n8n-panel/scripts/*.sh
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

#################################
# 6. POSTGRESQL CLEAN + SETUP
#################################
echo "Configuring PostgreSQL..."
# Force md5 auth for local connections
sed -i "s/^local\s\+all\s\+all\s\+peer/local all all md5/" /etc/postgresql/*/main/pg_hba.conf
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
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR"

cd "$APP_DIR"

#################################
# 8. LARAVEL CONFIG
#################################
sudo -u www-data cp .env.example .env

sudo -u www-data sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=pgsql|" .env
sudo -u www-data sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" .env
sudo -u www-data sed -i "s|^DB_PORT=.*|DB_PORT=5432|" .env
sudo -u www-data sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
sudo -u www-data sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
sudo -u www-data sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" .env
sudo -u www-data sed -i "s|^APP_URL=.*|APP_URL=https://${HOSTNAME_FQDN}:${PANEL_PORT}|" .env

# Make storage and cache writable
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Install dependencies and setup Laravel
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan key:generate --force
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --force
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

systemctl restart php8.2-fpm

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
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
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
