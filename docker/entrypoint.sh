#!/bin/sh
set -eu
php /var/www/scripts/migrate.php
exec docker-php-entrypoint "$@"
