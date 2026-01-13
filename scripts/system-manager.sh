#!/bin/bash

# system-manager.sh
# Usage: ./system-manager.sh --action={hostname|reboot|service-status|service-action} [ARGS...]

set -e

ACTION=""
VALUE=""
SERVICE=""
CMD=""

for i in "$@"
do
case $i in
    --action=*)
    ACTION="${i#*=}"
    ;;
    --value=*)
    VALUE="${i#*=}"
    ;;
    --service=*)
    SERVICE="${i#*=}"
    ;;
    --cmd=*)
    CMD="${i#*=}"
    ;;
    *)
    ;;
esac
done

if [ -z "$ACTION" ]; then
    echo "Error: Action is required."
    exit 1
fi

case $ACTION in
    hostname)
        if [ -z "$VALUE" ]; then
            echo "Error: Hostname value required."
            exit 1
        fi
        hostnamectl set-hostname "$VALUE"
        echo "Hostname set to $VALUE"
        ;;
    reboot)
        # Background reboot to allow script to return?
        # Or just reboot. PHP Process might hang or fail if connection drops immediately.
        # "sleep 2 && reboot" in background is standard.
        (sleep 2 && reboot) &
        echo "Reboot scheduled in 2 seconds."
        ;;
    service-status)
        if [ -z "$SERVICE" ]; then
            echo "Error: Service name required."
            exit 1
        fi
        if systemctl is-active --quiet "$SERVICE"; then
            echo "active"
        else
            echo "inactive"
        fi
        ;;
    service-action)
        if [ -z "$SERVICE" ] || [ -z "$CMD" ]; then
            echo "Error: Service and Command required."
            exit 1
        fi
        case $CMD in
            start|stop|restart|reload)
                systemctl "$CMD" "$SERVICE"
                echo "Service $SERVICE $CMD executed."
                ;;
            *)
                echo "Invalid service command: $CMD"
                exit 1
                ;;
        esac
        ;;
    *)
        echo "Invalid action: $ACTION"
        exit 1
        ;;
esac
