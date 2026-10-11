#!/bin/sh
set -e

mkdir -p /var/www/html/storage
chown www-data:www-data /var/www/html/storage

(
    while true; do
        sleep "${CLEANUP_INTERVAL:-900}"
        runuser -u www-data -- php /var/www/html/bin/cleanup.php || true
    done
) &

exec "$@"
