#!/bin/bash

# manage-container.sh
# Usage: ./manage-container.sh --name=NAME --action={start|stop|restart}

set -e

NAME=""
ACTION=""

for i in "$@"
do
case $i in
    --name=*)
    NAME="${i#*=}"
    ;;
    --action=*)
    ACTION="${i#*=}"
    ;;
    *)
    ;;
esac
done

if [ -z "$NAME" ] || [ -z "$ACTION" ]; then
    echo "Error: Name and Action are required."
    exit 1
fi

case $ACTION in
    start)
        docker start "$NAME"
        ;;
    stop)
        docker stop "$NAME"
        ;;
    restart)
        docker restart "$NAME"
        ;;
    *)
        echo "Invalid action: $ACTION"
        exit 1
        ;;
esac

echo "Container $NAME $ACTION completed."
