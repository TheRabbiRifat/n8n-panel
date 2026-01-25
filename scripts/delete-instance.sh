#!/bin/bash

# delete-instance.sh
# Usage: ./delete-instance.sh --id=ID --name=NAME --domain=DOMAIN

set -e

ID=""
NAME=""
DOMAIN=""

for i in "$@"
do
case $i in
    --id=*)
    ID="${i#*=}"
    ;;
    --name=*)
    NAME="${i#*=}"
    ;;
    --domain=*)
    DOMAIN="${i#*=}"
    ;;
    *)
    ;;
esac
done

if [ -z "$NAME" ]; then
    echo "Error: Name is required."
    exit 1
fi

echo "Deleting instance $NAME..."

# 0. PostgreSQL Cleanup
SAFE_NAME=$(echo "$NAME" | tr -cd 'a-z0-9')
DB_USER="n8n_${SAFE_NAME}"
DB_NAME="n8n_${SAFE_NAME}"

# Check if psql exists and runs (assuming sudo access)
if command -v psql &> /dev/null; then
    echo "Removing PostgreSQL database and user..."
    sudo -u postgres psql -c "DROP DATABASE IF EXISTS ${DB_NAME}" || true
    sudo -u postgres psql -c "DROP USER IF EXISTS ${DB_USER}" || true
fi

# 1. Remove Container
if docker ps -a --format '{{.Names}}' | grep -q "^${NAME}$"; then
    docker rm -f "$NAME" || true
else
    echo "Container $NAME not found, skipping."
fi

# 2. Remove Nginx Config
if [ ! -z "$DOMAIN" ]; then
    # Legacy path cleanup (if exists)
    rm -f "/etc/nginx/sites-available/$DOMAIN"
    rm -f "/etc/nginx/sites-enabled/$DOMAIN"

    # New path cleanup
    if [ ! -z "$NAME" ]; then
        rm -f "/var/lib/n8n/nginx/${NAME}.conf"
    fi

    # 3. Certbot Cleanup
    if command -v certbot &> /dev/null; then
        certbot delete --cert-name "$DOMAIN" --non-interactive || true
    fi

    systemctl reload nginx || true
fi

# 4. Remove Volume
# Use ID if present, else fallback to NAME
if [ ! -z "$ID" ]; then
    VOLUME_PATH="/var/lib/n8n/instances/${ID}"
else
    VOLUME_PATH="/var/lib/n8n/instances/${NAME}"
fi

if [[ "$VOLUME_PATH" == /var/lib/n8n/instances/* ]] && [ ${#VOLUME_PATH} -gt 23 ]; then
    rm -rf "$VOLUME_PATH"
fi

echo "Instance $NAME deleted."
