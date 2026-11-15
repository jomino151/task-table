#!/usr/bin/env bash
# Local dev stack for the task-table plugin (podman or docker).
#   WordPress  http://localhost:8888   (admin / admin)
#   Mailpit    http://localhost:8025   (catches every email the plugin sends)
# The repo is bind-mounted as wp-content/plugins/task-table, so `npm run start` rebuilds are live.
# Usage: dev/up.sh        start (idempotent) and provision
#        dev/down.sh      stop and remove everything (database included)
set -euo pipefail

ENGINE="${ENGINE:-$(command -v podman || command -v docker)}"
POD=task-table-dev
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WP_PORT="${WP_PORT:-8888}"
MAIL_PORT="${MAIL_PORT:-8025}"
SITE_URL="http://localhost:${WP_PORT}"

if [ ! -d "$ROOT/build" ]; then
  echo "build/ missing, running npm run build" >&2
  (cd "$ROOT" && npm run build)
fi

if ! "$ENGINE" pod exists "$POD" 2>/dev/null; then
  "$ENGINE" pod create --name "$POD" -p "${WP_PORT}:80" -p "${MAIL_PORT}:8025"
  "$ENGINE" volume create "${POD}-wp" >/dev/null
  "$ENGINE" volume create "${POD}-db" >/dev/null

  "$ENGINE" run -d --pod "$POD" --name "${POD}-db" \
    -e MARIADB_ROOT_PASSWORD=root -e MARIADB_DATABASE=wordpress \
    -e MARIADB_USER=wordpress -e MARIADB_PASSWORD=wordpress \
    -v "${POD}-db:/var/lib/mysql" docker.io/library/mariadb:11

  "$ENGINE" run -d --pod "$POD" --name "${POD}-mail" docker.io/axllent/mailpit:latest

  "$ENGINE" run -d --pod "$POD" --name "${POD}-wp" \
    -e WORDPRESS_DB_HOST=127.0.0.1 -e WORDPRESS_DB_USER=wordpress \
    -e WORDPRESS_DB_PASSWORD=wordpress -e WORDPRESS_DB_NAME=wordpress \
    -e WORDPRESS_DEBUG=1 \
    -e WORDPRESS_CONFIG_EXTRA="define('WP_DEBUG_LOG', true); define('WP_DEBUG_DISPLAY', false); define('WP_ENVIRONMENT_TYPE', 'local');" \
    -v "${POD}-wp:/var/www/html" \
    -v "$ROOT:/var/www/html/wp-content/plugins/task-table:Z" \
    -v "$ROOT/dev/mu-plugins:/var/www/html/wp-content/mu-plugins:Z" \
    docker.io/library/wordpress:php8.3-apache
else
  "$ENGINE" pod start "$POD" >/dev/null
fi

wp() {
  "$ENGINE" run --rm --pod "$POD" --user 33:33 \
    -e WORDPRESS_DB_HOST=127.0.0.1 -e WORDPRESS_DB_USER=wordpress \
    -e WORDPRESS_DB_PASSWORD=wordpress -e WORDPRESS_DB_NAME=wordpress \
    -v "${POD}-wp:/var/www/html" \
    -v "$ROOT:/var/www/html/wp-content/plugins/task-table:Z" \
    -v "$ROOT/dev/mu-plugins:/var/www/html/wp-content/mu-plugins:Z" \
    docker.io/library/wordpress:cli wp "$@"
}

echo "Waiting for WordPress files and database..."
for _ in $(seq 1 60); do
  wp core version >/dev/null 2>&1 && wp db check >/dev/null 2>&1 && break
  sleep 2
done

if ! wp core is-installed >/dev/null 2>&1; then
  wp core install --url="$SITE_URL" --title="Task Table Dev" \
    --admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email
  wp option update users_can_register 1          # open registration, as on the affected sites
  wp option update default_role subscriber
  wp rewrite structure '/%postname%/' --hard
  wp plugin activate task-table
  wp user create subscriber subscriber@example.test --role=subscriber --user_pass=subscriber
  wp user create editor editor@example.test --role=editor --user_pass=editor
  wp post create --post_type=page --post_status=publish --post_title="Task Table" \
    --post_content='<!-- wp:task-table/task-table {"align":"wide"} /--><!-- wp:task-table/new-event-signup /-->'
  wp eval-file wp-content/plugins/task-table/dev/seed.php
fi

echo
echo "WordPress: $SITE_URL/wp-admin  (admin/admin, subscriber/subscriber, editor/editor)"
echo "Frontend:  $SITE_URL/task-table/"
echo "Mailpit:   http://localhost:${MAIL_PORT}"
echo "wp-cli:    dev/wp.sh <command>"
