#!/bin/bash

# db-manager.sh
# Usage: ./db-manager.sh --action={export|import} --db-name=NAME [--file=PATH] [--db-user=USER]

set -e

ACTION=""
DB_NAME=""
DB_USER=""
FILE=""

for i in "$@"
do
case $i in
    --action=*)
    ACTION="${i#*=}"
    ;;
    --db-name=*)
    DB_NAME="${i#*=}"
    ;;
    --db-user=*)
    DB_USER="${i#*=}"
    ;;
    --file=*)
    FILE="${i#*=}"
    ;;
    *)
    ;;
esac
done

if [ -z "$ACTION" ] || [ -z "$DB_NAME" ]; then
    echo "Error: Action and DB Name are required."
    exit 1
fi

# Security: Validate DB Name to prevent SQL Injection
if [[ ! "$DB_NAME" =~ ^[a-zA-Z0-9_]+$ ]]; then
    echo "Error: Invalid characters in DB Name. Only alphanumeric and underscores are allowed."
    exit 1
fi

# Security: Validate DB User if provided
if [ ! -z "$DB_USER" ]; then
    if [[ ! "$DB_USER" =~ ^[a-zA-Z0-9_]+$ ]]; then
        echo "Error: Invalid characters in DB User. Only alphanumeric and underscores are allowed."
        exit 1
    fi
fi

case $ACTION in
    export)
        # Dump to stdout
        # Use --no-owner --no-acl to ensure portability between instances
        # Quote DB name to handle special characters if any
        sudo -u postgres pg_dump --no-owner --no-acl "\"$DB_NAME\""
        ;;
    import)
        if [ -z "$FILE" ]; then
            echo "Error: File path is required for import."
            exit 1
        fi

        # If DB_USER is provided, we use it for ownership reset, else assume DB_NAME matches user (n8n convention)
        if [ -z "$DB_USER" ]; then
            DB_USER="$DB_NAME"
        fi

        # Recreate DB (Drop/Create/Grant)
        # Check if DB exists first to avoid error? No, DROP IF EXISTS handles it.
        # But we need to terminate connections first usually?
        # The PHP script stops the container, so connections should be minimal.

        sudo -u postgres psql -c "DROP DATABASE IF EXISTS \"${DB_NAME}\";"
        sudo -u postgres psql -c "CREATE DATABASE \"${DB_NAME}\" OWNER \"${DB_USER}\";"
        sudo -u postgres psql -c "GRANT ALL PRIVILEGES ON DATABASE \"${DB_NAME}\" TO \"${DB_USER}\";"

        # Import Dump
        # Switch to the target user role so created objects are owned by them correctly
        # This avoids the need for REASSIGN OWNED which fails on system objects
        (echo "SET ROLE \"${DB_USER}\";"; cat "$FILE") | sudo -u postgres psql -d "$DB_NAME"

        # Ensure schema ownership is correct (ignore errors if already correct)
        sudo -u postgres psql -d "$DB_NAME" -c "ALTER SCHEMA public OWNER TO \"${DB_USER}\";" || true

        echo "Import successful."
        ;;
    *)
        echo "Invalid action: $ACTION"
        exit 1
        ;;
esac
