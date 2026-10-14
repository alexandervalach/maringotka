#!/bin/sh
set -eu

RUNTIME_DIR="/var/www/html/var"

mkdir -p \
    "$RUNTIME_DIR/temp" \
    "$RUNTIME_DIR/log" \
    "$RUNTIME_DIR/monitoring"

chown -R www-data:www-data \
    "$RUNTIME_DIR/temp" \
    "$RUNTIME_DIR/log" \
    "$RUNTIME_DIR/monitoring"

chmod -R u+rwX \
    "$RUNTIME_DIR/temp" \
    "$RUNTIME_DIR/log" \
    "$RUNTIME_DIR/monitoring"

exec docker-php-entrypoint "$@"