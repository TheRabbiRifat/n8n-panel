#!/bin/bash

# get-system-status.sh
# Usage: ./get-system-status.sh --type={os|kernel|ips|uptime|load|memory}

set -e

TYPE=""

for i in "$@"
do
case $i in
    --type=*)
    TYPE="${i#*=}"
    ;;
    *)
    ;;
esac
done

if [ -z "$TYPE" ]; then
    echo "Error: Type is required."
    exit 1
fi

case $TYPE in
    os)
        cat /etc/os-release | grep PRETTY_NAME | cut -d= -f2 | tr -d '"'
        ;;
    kernel)
        uname -r
        ;;
    ips)
        hostname -I
        ;;
    uptime)
        uptime -p
        ;;
    load)
        cat /proc/loadavg | awk '{print $1 " " $2 " " $3}'
        ;;
    memory)
        free -m
        ;;
    hostname)
        hostname
        ;;
    *)
        echo "Invalid type: $TYPE"
        exit 1
        ;;
esac
