#!/bin/bash

# docker-utils.sh
# Usage: ./docker-utils.sh --action={list|inspect|stats|logs|info|update} [ARGS...]

set -e

ACTION=""
ID=""
LINES="100"
ARGS=()

for i in "$@"
do
case $i in
    --action=*)
    ACTION="${i#*=}"
    ;;
    --id=*)
    ID="${i#*=}"
    ;;
    --lines=*)
    LINES="${i#*=}"
    ;;
    --arg=*)
    ARGS+=("${i#*=}")
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
    list)
        docker ps -a --format "{{.ID}}|{{.Names}}|{{.Image}}|{{.Status}}|{{.State}}|{{.Ports}}"
        ;;
    inspect-batch)
        if [ ${#ARGS[@]} -eq 0 ]; then echo "Error: IDs required"; exit 1; fi
        docker inspect "${ARGS[@]}"
        ;;
    inspect)
        if [ -z "$ID" ]; then echo "Error: ID required"; exit 1; fi
        docker inspect "$ID"
        ;;
    inspect-format)
        if [ -z "$ID" ]; then echo "Error: ID required"; exit 1; fi
        # ARGS[0] should be format
        docker inspect --format "${ARGS[0]}" "$ID"
        ;;
    stats)
        if [ -z "$ID" ]; then
             echo "Error: ID required"; exit 1;
        fi
        # Check if ARGS[0] is format, else default json
        if [ ! -z "${ARGS[0]}" ]; then
             docker stats --no-stream --format "${ARGS[0]}" "$ID"
        else
             docker stats --no-stream --format "{{json .}}" "$ID"
        fi
        ;;
    logs)
        if [ -z "$ID" ]; then echo "Error: ID required"; exit 1; fi
        docker logs --tail "$LINES" "$ID"
        ;;
    info)
        docker info
        ;;
    update)
        if [ -z "$ID" ]; then echo "Error: ID required"; exit 1; fi
        # Pass ARGS to update command
        # This allows arbitrary flags like --memory, --cpus
        docker update "${ARGS[@]}" "$ID"
        ;;
    *)
        echo "Invalid action: $ACTION"
        exit 1
        ;;
esac
