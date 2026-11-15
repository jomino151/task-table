#!/usr/bin/env bash
# Run wp-cli against the dev stack: dev/wp.sh plugin list
ENGINE="${ENGINE:-$(command -v podman || command -v docker)}"
POD=task-table-dev
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
exec "$ENGINE" run --rm -i --pod "$POD" --user 33:33 \
  -e WORDPRESS_DB_HOST=127.0.0.1 -e WORDPRESS_DB_USER=wordpress \
  -e WORDPRESS_DB_PASSWORD=wordpress -e WORDPRESS_DB_NAME=wordpress \
  -v "${POD}-wp:/var/www/html" \
  -v "$ROOT:/var/www/html/wp-content/plugins/task-table:Z" \
  -v "$ROOT/dev/mu-plugins:/var/www/html/wp-content/mu-plugins:Z" \
  docker.io/library/wordpress:cli wp "$@"
