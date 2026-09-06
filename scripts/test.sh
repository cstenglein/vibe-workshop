#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
command -v docker >/dev/null
work=$(mktemp -d)
project="todo-test-$(date +%s)-$$"
export DB_NAME=todos_test DB_USER=test_user
DB_PASSWORD="test-$RANDOM-$RANDOM"
DB_ROOT_PASSWORD="root-test-$RANDOM-$RANDOM"
export DB_PASSWORD DB_ROOT_PASSWORD APP_PORT=0
compose=(docker compose --env-file /dev/null -p "$project" -f docker-compose.yml)
cleanup() {
    status=$?
    trap - EXIT
    if (( status != 0 )); then "${compose[@]}" logs --tail=80; fi
    "${compose[@]}" down --volumes --remove-orphans >/dev/null
    rm -rf "$work"
    exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
"${compose[@]}" config --quiet
"${compose[@]}" build
"${compose[@]}" run --rm --no-deps --entrypoint sh app -c 'set -e; for file in /var/www/app/*.php /var/www/app/public/*.php /var/www/scripts/*.php; do php -l "$file"; done'
"${compose[@]}" up -d --wait --wait-timeout 240
"${compose[@]}" exec -T app php -- exercise < tests/http_test.php
"${compose[@]}" exec -T app php /var/www/scripts/migrate.php
"${compose[@]}" exec -T app php /var/www/scripts/migrate.php
# Applied migrations must not be silently changed; a CLI failure must stop startup.
"${compose[@]}" exec -T app sh -c 'printf "\n-- changed\n" >> /var/www/migrations/001_create_todos.sql'
if "${compose[@]}" exec -T app php /var/www/scripts/migrate.php > "$work/migration-error" 2>&1; then
    printf 'Veränderte Migration wurde unerwartet akzeptiert.\n' >&2
    exit 1
fi
"${compose[@]}" up -d --force-recreate --wait --wait-timeout 240
"${compose[@]}" exec -T app php -- persistence < tests/http_test.php
"${compose[@]}" exec -T app php -r 'require "/var/www/app/bootstrap.php"; if ((int) database()->query("SELECT COUNT(*) FROM schema_migrations")->fetchColumn() !== count(glob("/var/www/migrations/*.sql"))) { exit(1); }'
"${compose[@]}" stop db
"${compose[@]}" exec -T app php -- unavailable < tests/http_test.php
printf 'Alle Tests erfolgreich.\n'
