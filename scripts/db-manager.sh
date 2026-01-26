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

case $ACTION in
    export)
        # Dump to stdout
        # Use --no-owner --no-acl to ensure portability between instances
        sudo -u postgres pg_dump --no-owner --no-acl "$DB_NAME"
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
        # Using cat to pipe prevents psql file permission issues if running as postgres user
        cat "$FILE" | sudo -u postgres psql -d "$DB_NAME"

        # Fix Ownership (Objects created by postgres must be transferred to db_user)
        sudo -u postgres psql -d "$DB_NAME" -c "REASSIGN OWNED BY postgres TO \"${DB_USER}\";"
        sudo -u postgres psql -d "$DB_NAME" -c "ALTER SCHEMA public OWNER TO \"${DB_USER}\";"

        echo "Import successful."
        ;;
    *)
        echo "Invalid action: $ACTION"
        exit 1
        ;;
esac
