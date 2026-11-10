#!/bin/sh
set -e
cd /var/www/html

mkdir -p logs public/uploads/covers
chown -R www-data:www-data logs public/uploads 2>/dev/null || true
chmod -R a+rwX logs public/uploads 2>/dev/null || true

if [ "$1" = "apache2-foreground" ]; then
    php bin/migrate.php
fi

exec "$@"
