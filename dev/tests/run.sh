#!/usr/bin/env bash
# Integration tests against the local dev stack (dev/up.sh must be running, plugin built).
# Usage: dev/tests/run.sh      exit code = number of failed checks
set -uo pipefail
cd "$(dirname "$0")/../.."
BASE="${BASE:-http://localhost:8888}"
MAILPIT="${MAILPIT:-http://localhost:8025}"
API="$BASE/wp-json"
TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
WP=dev/wp.sh
FAILS=0; PASSES=0

pass() { PASSES=$((PASSES + 1)); echo "  ok   $1"; }
fail() { FAILS=$((FAILS + 1)); echo "  FAIL $1${2:+ ($2)}"; }
check() { # check "name" expected actual
  if [ "$2" = "$3" ]; then pass "$1"; else fail "$1" "expected $2, got $3"; fi
}
sql() { $WP db query "$1" --skip-column-names 2>/dev/null | tr -d '\r'; }
reset_limits() { $WP transient delete --all >/dev/null 2>&1; }
mail_count() { curl -s "$MAILPIT/api/v1/search?query=to:$1" | python3 -c 'import sys,json;print(json.load(sys.stdin)["messages_count"])'; }
clear_mail() { curl -s -X DELETE "$MAILPIT/api/v1/messages" >/dev/null; }

login() { # login user pass -> prints nonce, cookie jar at $TMP/$1
  curl -s -o /dev/null -c "$TMP/$1" -b "wordpress_test_cookie=WP%20Cookie%20check" \
    -d "log=$1&pwd=$2&wp-submit=Log+In&testcookie=1" "$BASE/wp-login.php"
  curl -s -b "$TMP/$1" "$BASE/wp-admin/admin-ajax.php?action=rest-nonce"
}
status() { curl -s -o "$TMP/body" -w '%{http_code}' "$@"; }
post_json() { status -X POST -H 'Content-Type: application/json' -d "$2" "$API$1"; }

$WP eval 'file_put_contents(WP_CONTENT_DIR . "/debug.log", "");' >/dev/null
reset_limits; clear_mail
ACTIVE=$(sql "SELECT MIN(id) FROM wp_task_table_events WHERE archived=0")
ARCHIVED=$(sql "SELECT MIN(id) FROM wp_task_table_events WHERE archived=1")

echo "Export authorization (CWE-862)"
EXPORT="$API/task-table/v1/events/export?archived=all"
check "anonymous gets 401" 401 "$(status "$EXPORT&format=xml")"
for u in subscriber editor; do
  n=$(login $u $u)
  check "$u gets 403" 403 "$(status -b "$TMP/$u" -H "X-WP-Nonce: $n" "$EXPORT&format=xml")"
done
n=$(login admin admin)
check "admin gets 200 (xlsx)" 200 "$(status -b "$TMP/admin" -H "X-WP-Nonce: $n" "$EXPORT")"
if python3 -c "import zipfile,sys;zipfile.ZipFile(sys.argv[1]).testzip()" "$TMP/body" 2>/dev/null; then
  pass "xlsx body is a valid zip, not JSON"; else fail "xlsx body is a valid zip, not JSON"; fi
check "admin gets 200 (xml)" 200 "$(status -b "$TMP/admin" -H "X-WP-Nonce: $n" "$EXPORT&format=xml")"
check "xml body is raw XML" '<?xml' "$(head -c 5 "$TMP/body")"

echo "Public signup endpoint"
check "invalid email rejected" 400 "$(post_json /event-signups/v1/submit "{\"event_id\":$ACTIVE,\"user_name\":\"A\",\"user_email\":\"nope\"}")"
check "missing fields rejected" 400 "$(post_json /event-signups/v1/submit '{}')"
check "archived event rejected" 404 "$(post_json /event-signups/v1/submit "{\"event_id\":$ARCHIVED,\"user_name\":\"A\",\"user_email\":\"a@example.test\"}")"
reset_limits
check "valid signup accepted" 200 "$(post_json /event-signups/v1/submit "{\"event_id\":$ACTIVE,\"user_name\":\"Test Person\",\"user_email\":\"tp@example.test\",\"free_text\":\"hi\"}")"
sleep 1; check "moderator notified" 1 "$(mail_count admin@example.test)"
for _ in 1 2 3 4; do post_json /event-signups/v1/submit "{\"event_id\":$ACTIVE,\"user_name\":\"B\",\"user_email\":\"b@example.test\"}" >/dev/null; done
check "6th signup in window is rate limited" 429 "$(post_json /event-signups/v1/submit "{\"event_id\":$ACTIVE,\"user_name\":\"B\",\"user_email\":\"b@example.test\"}")"

echo "Subscribe / double opt-in"
reset_limits; clear_mail
sql "DELETE FROM wp_task_table_subscribers WHERE email LIKE '%@example.test'" >/dev/null
CAT=$(sql "SELECT MIN(id) FROM wp_task_table_categories")
sql "INSERT INTO wp_task_table_subscribers (email, confirmed, created_at, updated_at) VALUES ('known@example.test', 1, NOW(), NOW())" >/dev/null
KNOWN_ID=$(sql "SELECT id FROM wp_task_table_subscribers WHERE email='known@example.test'")
sql "INSERT INTO wp_task_table_subscriber_categories (subscriber_id, category_id, created_at) VALUES ($KNOWN_ID, $CAT, NOW())" >/dev/null

post_json /task-table/v1/subscribers '{"email":"new@example.test"}' >/dev/null; cp "$TMP/body" "$TMP/new.json"
CAT2=$(sql "SELECT id FROM wp_task_table_categories WHERE slug='test-other'")
if [ -z "$CAT2" ]; then
  sql "INSERT INTO wp_task_table_categories (name, slug, description, sort_order, created_at, updated_at) VALUES ('Other', 'test-other', '', 99, NOW(), NOW())" >/dev/null
  CAT2=$(sql "SELECT id FROM wp_task_table_categories WHERE slug='test-other'")
fi
post_json /task-table/v1/subscribers "{\"email\":\"known@example.test\",\"categories\":[$CAT2]}" >/dev/null
check "known and new address get identical responses" "$(cat "$TMP/new.json")" "$(cat "$TMP/body")"
check "confirmed subscriber's categories unchanged" "$CAT" "$(sql "SELECT GROUP_CONCAT(category_id) FROM wp_task_table_subscriber_categories WHERE subscriber_id=$KNOWN_ID")"

for _ in 1 2 3 4 5; do post_json /task-table/v1/subscribers '{"email":"victim@example.test"}' >/dev/null; done
sleep 1; check "max 3 confirmation mails per address per day" 3 "$(mail_count victim@example.test)"

status "$API/task-table/v1/subscribers/confirm?email=nobody@example.test&token=x" >/dev/null; cp "$TMP/body" "$TMP/unknown.json"
s=$(status "$API/task-table/v1/subscribers/confirm?email=new@example.test&token=x")
check "wrong token rejected" 400 "$s"
check "unknown vs known email indistinguishable on confirm" "$(cat "$TMP/unknown.json")" "$(cat "$TMP/body")"

TOKEN=$(sql "SELECT confirmation_token FROM wp_task_table_subscribers WHERE email='victim@example.test'")
sql "UPDATE wp_task_table_subscribers SET updated_at = DATE_SUB(NOW(), INTERVAL 8 DAY) WHERE email='victim@example.test'" >/dev/null
check "expired token rejected" 400 "$(status "$API/task-table/v1/subscribers/confirm?email=victim%40example.test&token=$TOKEN")"
TOKEN=$(sql "SELECT confirmation_token FROM wp_task_table_subscribers WHERE email='new@example.test'")
check "valid token confirms" 200 "$(status "$API/task-table/v1/subscribers/confirm?email=new%40example.test&token=$TOKEN")"
check "subscriber marked confirmed" 1 "$(sql "SELECT confirmed FROM wp_task_table_subscribers WHERE email='new@example.test'")"

echo "Admin pages"
for page in task-table task-table-signups task-table-subscribers task-table-categories task-table-email-settings; do
  check "admin page $page loads" 200 "$(status -b "$TMP/admin" "$BASE/wp-admin/admin.php?page=$page")"
done
check "subscriber cannot open admin page" 403 "$(status -b "$TMP/subscriber" "$BASE/wp-admin/admin.php?page=task-table")"

echo "PHP log"
LOG=$($WP eval 'echo @file_get_contents(WP_CONTENT_DIR . "/debug.log");' | grep -i "task-table" || true)
if [ -z "$LOG" ]; then pass "no PHP warnings/errors from the plugin"; else fail "no PHP warnings/errors from the plugin"; echo "$LOG" | sort -u | head -5; fi

reset_limits
echo
echo "$PASSES passed, $FAILS failed"
exit "$FAILS"
