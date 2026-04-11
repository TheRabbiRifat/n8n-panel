#!/bin/bash
set -e

echo "Starting n8n Panel Updater..."

# Check if running as root
if [ "$(id -u)" -ne 0 ]; then
    echo "❌ Error: This script must be run as root."
    exit 1
fi

APP_DIR="/var/n8n-panel"
TMP_DIR="/tmp/n8n-panel-update"
ZIP_URL="https://github.com/TheRabbiRifat/n8n-panel/archive/refs/heads/main.zip"

if [ ! -d "$APP_DIR" ]; then
    echo "❌ Error: Panel directory $APP_DIR not found. Is it installed?"
    exit 1
fi

echo "1. Downloading latest release..."
rm -rf "$TMP_DIR"
mkdir -p "$TMP_DIR"
curl -fsSL "$ZIP_URL" -o "$TMP_DIR/panel.zip"

echo "2. Extracting files..."
unzip -q "$TMP_DIR/panel.zip" -d "$TMP_DIR"
SRC_DIR="$(find "$TMP_DIR" -mindepth 1 -maxdepth 1 -type d | head -n1)"

echo "3. Updating files (preserving .env and storage)..."
rsync -av --exclude='.env' --exclude='storage/' --exclude='.git/' "$SRC_DIR/" "$APP_DIR/"

echo "4. Setting permissions..."
chown -R www-data:www-data "$APP_DIR"
chmod -R 775 "$APP_DIR"

cd "$APP_DIR" || exit 1

echo "5. Installing dependencies via Composer..."
sudo -u www-data composer install --no-dev --optimize-autoloader

echo "6. Running database migrations..."
sudo -u www-data php artisan migrate --force

echo "7. Clearing caches..."
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache

echo "8. Cleaning up..."
rm -rf "$TMP_DIR"

echo "✅ Panel update completed successfully."