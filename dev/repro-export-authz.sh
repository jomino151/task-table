#!/usr/bin/env bash
# Reproduction / regression check for the events export missing-authorization bug (CWE-862).
# Logs in as a Subscriber on the local dev stack and calls GET /wp-json/task-table/v1/events/export.
# Exit 0 = protected (403), exit 1 = vulnerable (200 with event data).
set -euo pipefail
BASE="${BASE:-http://localhost:8888}"
USER_LOGIN="${USER_LOGIN:-subscriber}"
USER_PASS="${USER_PASS:-subscriber}"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT

curl -s -o /dev/null -c "$TMP/jar" -b "wordpress_test_cookie=WP%20Cookie%20check" \
  -d "log=${USER_LOGIN}&pwd=${USER_PASS}&wp-submit=Log+In&testcookie=1" "$BASE/wp-login.php"
NONCE="$(curl -s -b "$TMP/jar" "$BASE/wp-admin/admin-ajax.php?action=rest-nonce")"

echo "> GET $BASE/wp-json/task-table/v1/events/export?format=xml&archived=all  (as $USER_LOGIN)"
STATUS="$(curl -s -o "$TMP/body" -w '%{http_code}' -b "$TMP/jar" -H "X-WP-Nonce: $NONCE" \
  "$BASE/wp-json/task-table/v1/events/export?format=xml&archived=all")"
echo "< HTTP $STATUS"
head -c 400 "$TMP/body"; echo

if [ "$STATUS" = "200" ]; then
  echo "VULNERABLE: $USER_LOGIN can export all events"; exit 1
fi
echo "OK: export denied for $USER_LOGIN"
