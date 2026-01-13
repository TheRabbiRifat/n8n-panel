#!/bin/bash

# delete-instance.sh
# Usage: ./delete-instance.sh --name=NAME --domain=DOMAIN

set -e

NAME=""
DOMAIN=""

for i in "$@"
do
case $i in
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

# 1. Remove Container
if docker ps -a --format '{{.Names}}' | grep -q "^${NAME}$"; then
    docker rm -f "$NAME" || true
else
    echo "Container $NAME not found, skipping."
fi

# 2. Remove Nginx Config
if [ ! -z "$DOMAIN" ]; then
    rm -f "/etc/nginx/sites-available/$DOMAIN"
    rm -f "/etc/nginx/sites-enabled/$DOMAIN"

    # 3. Certbot Cleanup
    if command -v certbot &> /dev/null; then
        certbot delete --cert-name "$DOMAIN" --non-interactive || true
    fi

    systemctl reload nginx || true
fi

# 4. Remove Volume
VOLUME_PATH="/var/lib/n8n/instances/${NAME}"
if [[ "$VOLUME_PATH" == /var/lib/n8n/instances/* ]] && [ ${#VOLUME_PATH} -gt 23 ]; then
    rm -rf "$VOLUME_PATH"
fi

echo "Instance $NAME deleted."
