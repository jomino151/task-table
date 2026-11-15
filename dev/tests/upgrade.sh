#!/usr/bin/env bash
# Upgrade test: install the previous release (default: the code on `main`, deployed as 1.1.0), create
# data shaped like a production site, then swap in the working-tree code and check that existing
# subscribers, signups, templates and links keep working.
# Runs in its own stack (pod task-table-upgrade, ports 8890/8026) and removes it afterwards.
# Usage: dev/tests/upgrade.sh [old-git-ref]     KEEP=1 keeps the stack for inspection
set -uo pipefail
cd "$(dirname "$0")/../.."
ROOT="$(pwd)"
OLD_REF="${1:-main}"
export POD=task-table-upgrade WP_PORT=8890 MAIL_PORT=8026
export WP_IMAGE="${WP_IMAGE:-docker.io/library/wordpress:php8.4-apache}"   # production runs PHP 8.4
ENGINE="${ENGINE:-$(command -v podman || command -v docker)}"
BASE="http://localhost:$WP_PORT"; API="$BASE/wp-json"; MAILPIT="http://localhost:$MAIL_PORT"
TMP="$(mktemp -d)"; OLD="$TMP/old"
FAILS=0; PASSES=0
pass() { PASSES=$((PASSES + 1)); echo "  ok   $1"; }
fail() { FAILS=$((FAILS + 1)); echo "  FAIL $1${2:+ ($2)}"; }
check() { if [ "$2" = "$3" ]; then pass "$1"; else fail "$1" "expected $2, got $3"; fi; }
cleanup() { [ "${KEEP:-}" = 1 ] || dev/down.sh >/dev/null 2>&1; rm -rf "$TMP"; }
trap cleanup EXIT

wp() { PLUGIN_SRC="$PLUGIN_SRC" dev/wp.sh "$@"; }
sql() { wp db query "$1" --skip-column-names 2>/dev/null | tr -d '\r'; }
status() { curl -s -o "$TMP/body" -w '%{http_code}' "$@"; }
login() {
  curl -s -o /dev/null -c "$TMP/jar-$1" -b "wordpress_test_cookie=WP%20Cookie%20check" \
    -d "log=$1&pwd=$2&wp-submit=Log+In&testcookie=1" "$BASE/wp-login.php"
  curl -s -b "$TMP/jar-$1" "$BASE/wp-admin/admin-ajax.php?action=rest-nonce"
}
mails_to() { curl -s "$MAILPIT/api/v1/search?query=to:$1"; }
mail_text() { curl -s "$MAILPIT/api/v1/message/$1" | python3 -c 'import sys,json;print(json.load(sys.stdin)["Text"])' | tr -d '\r'; }
first_id() { python3 -c 'import sys,json;m=json.load(sys.stdin)["messages"];print(m[0]["ID"] if m else "")'; }

echo "== Build $OLD_REF in a temp dir"
mkdir -p "$OLD" && git archive "$OLD_REF" | tar -x -C "$OLD"
ln -s "$ROOT/node_modules" "$OLD/node_modules"
(cd "$OLD" && npx wp-scripts build --webpack-copy-php >/dev/null 2>&1) || { echo "old build failed"; exit 1; }
sed -i 's/^\( \* Version:           \).*/\11.1.0/' "$OLD/task-table.php"

echo "== Start old version"
dev/down.sh >/dev/null 2>&1
PLUGIN_SRC="$OLD" SKIP_SEED=1 dev/up.sh >/dev/null 2>&1 || { echo "stack failed"; exit 1; }
PLUGIN_SRC="$OLD"
check "old version active" "1.1.0" "$(wp plugin get task-table --field=version)"

echo "== Create production-like data with the old code"
wp language core install de_DE --activate >/dev/null 2>&1   # production runs de_DE
wp option update users_can_register 0 >/dev/null
wp eval-file - < dev/seed.php >/dev/null
CAT1=$(sql "SELECT MIN(id) FROM wp_task_table_categories")
sql "INSERT INTO wp_task_table_categories (name, slug, description, sort_order, created_at, updated_at) VALUES ('Second', 'second', '', 2, NOW(), NOW())"
CAT2=$(sql "SELECT id FROM wp_task_table_categories WHERE slug='second'")
EVENT=$(sql "SELECT MIN(id) FROM wp_task_table_events WHERE archived=0")

# Email templates saved through the old settings form (stores \" like production).
n=$(login admin admin)
PAGE="$BASE/wp-admin/admin.php?page=task-table-email-settings"
NONCE=$(curl -s -b "$TMP/jar-admin" "$PAGE" | grep -o 'name="task_table_email_settings_nonce" value="[^"]*"' | sed 's/.*value="//;s/"//')
curl -s -o /dev/null -b "$TMP/jar-admin" --data-urlencode "task_table_email_settings_nonce=$NONCE" \
  --data-urlencode 'task_table_email_signup_approved_subject=Zusage für "{event_title}"' \
  --data-urlencode 'task_table_email_signup_approved_message=Hallo {user_name}, du machst "{event_title}". {site_name}' \
  --data-urlencode 'task_table_email_new_event_message=Neu: {event_details} Grüße {site_name}' \
  --data-urlencode "signup_notification_recipients=[{\"email\":\"mod@example.test\",\"category_ids\":[]},{\"email\":\"mod2@example.test\",\"category_ids\":[$CAT2]}]" "$PAGE"
check "old code stored escaped quotes (precondition)" 1 "$(wp option get task_table_email_signup_approved_subject | grep -cF '\"')"

# Subscribers through the old public flow: two confirmed (different categories), one still pending.
for e in sub1 sub2 pending; do
  cats=$CAT1; [ $e = sub2 ] && cats=$CAT2
  curl -s -o /dev/null -X POST -H 'Content-Type: application/json' -d "{\"email\":\"$e@example.test\",\"categories\":[$cats]}" "$API/task-table/v1/subscribers"
done
for e in sub1 sub2; do
  t=$(sql "SELECT confirmation_token FROM wp_task_table_subscribers WHERE email='$e@example.test'")
  curl -s -o /dev/null "$BASE/?task_table_confirm_subscription=1&token=$t&email=$e%40example.test"
done
PENDING_TOKEN=$(sql "SELECT confirmation_token FROM wp_task_table_subscribers WHERE email='pending@example.test'")
check "old flow: 2 confirmed, 1 pending" "2 1" "$(sql "SELECT SUM(confirmed=1) FROM wp_task_table_subscribers") $(sql "SELECT SUM(confirmed=0) FROM wp_task_table_subscribers")"

# Signups through the old endpoint: two pending, one approved.
for who in Alex Kim Sam; do
  curl -s -o /dev/null -X POST -H 'Content-Type: application/json' \
    -d "{\"event_id\":$EVENT,\"user_name\":\"$who\",\"user_email\":\"$(echo $who | tr A-Z a-z)@example.test\",\"free_text\":\"Hallo\"}" "$API/event-signups/v1/submit"
done
SAM=$(sql "SELECT id FROM wp_task_table_signups WHERE user_name='Sam'")
sql "UPDATE wp_task_table_signups SET status='approved' WHERE id=$SAM"
sql "UPDATE wp_task_table_events SET wer_macht='Sam' WHERE id=$EVENT"

# A complete event created over REST but not yet announced (queued until the next save_post), like production.
curl -s -o /dev/null -X POST -b "$TMP/jar-admin" -H "X-WP-Nonce: $n" -H 'Content-Type: application/json' \
  -d "{\"wasAngefragt\":\"Queued event\",\"kontext\":\"ctx\",\"category_id\":$CAT1}" "$API/task-table/v1/events"
check "old: notification queued" 1 "$(wp option get task_table_pending_event_notifications --format=json | python3 -c 'import sys,json;print(len(json.load(sys.stdin)))')"
check "old db version" "1.6" "$(wp option get task_table_db_version)"
BEFORE=$(sql "SELECT CONCAT((SELECT COUNT(*) FROM wp_task_table_events),'/',(SELECT COUNT(*) FROM wp_task_table_signups),'/',(SELECT COUNT(*) FROM wp_task_table_subscribers),'/',(SELECT COUNT(*) FROM wp_task_table_subscriber_categories),'/',(SELECT COUNT(*) FROM wp_task_table_categories))")
curl -s -X DELETE "$MAILPIT/api/v1/messages" >/dev/null

echo "== Swap in the new code (database kept)"
"$ENGINE" rm -f "${POD}-wp" >/dev/null
PLUGIN_SRC="$ROOT" dev/up.sh >/dev/null 2>&1
PLUGIN_SRC="$ROOT"
wp eval 'file_put_contents(WP_CONTENT_DIR . "/debug.log", "");' >/dev/null
check "new version active" "$(grep -m1 'Version:' task-table.php | awk '{print $3}')" "$(wp plugin get task-table --field=version)"
check "front page renders" 200 "$(status "$BASE/task-table/")"
check "db upgraded to 1.7" "1.7" "$(wp option get task_table_db_version)"
check "data untouched (events/signups/subscribers/sub-cats/categories)" "$BEFORE" "$(sql "SELECT CONCAT((SELECT COUNT(*) FROM wp_task_table_events),'/',(SELECT COUNT(*) FROM wp_task_table_signups),'/',(SELECT COUNT(*) FROM wp_task_table_subscribers),'/',(SELECT COUNT(*) FROM wp_task_table_subscriber_categories),'/',(SELECT COUNT(*) FROM wp_task_table_categories))")"
check "escaped templates cleaned" 'Zusage für "{event_title}"' "$(wp option get task_table_email_signup_approved_subject)"
check "unescaped template unchanged" 'Neu: {event_details} Grüße {site_name}' "$(wp option get task_table_email_new_event_message)"
check "signup recipients kept" 2 "$(wp option get task_table_signup_notification_recipients --format=json | python3 -c 'import sys,json;print(len(json.load(sys.stdin)))')"

echo "== Existing users and links"
check "confirmation link mailed before the update still works" 200 "$(status "$API/task-table/v1/subscribers/confirm?email=pending%40example.test&token=$PENDING_TOKEN")"
n=$(login admin admin)
check "admin export works" 200 "$(status -b "$TMP/jar-admin" -H "X-WP-Nonce: $n" "$API/task-table/v1/events/export?archived=all")"
for u in subscriber editor; do
  wp user get $u >/dev/null 2>&1 || wp user create $u $u@example.test --role=$u --user_pass=$u >/dev/null
  nu=$(login $u $u)
  check "$u: export denied" 403 "$(status -b "$TMP/jar-$u" -H "X-WP-Nonce: $nu" "$API/task-table/v1/events/export?archived=all")"
done
curl -s -b "$TMP/jar-admin" "$BASE/wp-admin/admin.php?page=task-table" > "$TMP/admin.html"
check "admin menu is German" 1 "$(grep -q 'Aufgabentabelle' "$TMP/admin.html" && echo 1 || echo 0)"

echo "== Pending signup approved after the update"
ALEX=$(sql "SELECT id FROM wp_task_table_signups WHERE user_name='Alex'")
APPROVE=$(curl -s -b "$TMP/jar-admin" "$BASE/wp-admin/admin.php?page=task-table-signups" | grep -o "href=\"[^\"]*action=approve[^\"]*signup_id=$ALEX[^\"]*\"" | head -1 | sed 's/^href="//;s/"$//;s/&#038;/\&/g;s/&amp;/\&/g')
check "approve link present in admin" 1 "$([ -n "$APPROVE" ] && echo 1 || echo 0)"
check "approve pending signup" 200 "$(status -b "$TMP/jar-admin" "$APPROVE")"
check "signup marked approved" approved "$(sql "SELECT status FROM wp_task_table_signups WHERE id=$ALEX")"
sleep 2
MID=$(mails_to alex@example.test | first_id)
check "approval mail subject without backslashes" 'Zusage für "Workshop: Intro to Git"' "$(curl -s "$MAILPIT/api/v1/message/$MID" | python3 -c 'import sys,json;print(json.load(sys.stdin)["Subject"])')"

echo "== Queued notification goes out after the update"
wp post update "$(wp post list --post_type=page --name=task-table --field=ID 2>/dev/null || wp post list --post_type=post --field=ID | head -1)" --post_title="Task Table" >/dev/null 2>&1 || wp post create --post_title=trigger --post_status=draft >/dev/null
sleep 2
for e in sub1 sub2; do
  MID=$(mails_to $e@example.test | first_id)
  [ "$e" = sub1 ] && check "sub1 (matching category) notified" 1 "$([ -n "$MID" ] && echo 1 || echo 0)"
  [ "$e" = sub2 ] && check "sub2 (other category) not notified" "" "$MID"
done
MID=$(mails_to sub1@example.test | first_id)
TEXT=$(mail_text "$MID")
check "custom template got manage link appended" 1 "$(echo "$TEXT" | grep -c 'task_table_subscription=manage')"
check "List-Unsubscribe header" 1 "$(curl -s "$MAILPIT/api/v1/message/$MID/headers" | grep -c '"List-Unsubscribe"')"
LINK=$(echo "$TEXT" | grep -o 'http[^ ]*task_table_subscription=manage[^ ]*' | head -1)
check "pre-existing subscriber can open manage page" 200 "$(status "$LINK")"
check "pre-existing subscriber can unsubscribe" 200 "$(status -X POST -d task_table_action=unsubscribe "$LINK")"
check "unsubscribed" 0 "$(sql "SELECT COUNT(*) FROM wp_task_table_subscribers WHERE email='sub1@example.test'")"

echo "== PHP log after upgrade"
LOG=$(wp eval 'echo @file_get_contents(WP_CONTENT_DIR . "/debug.log");' | grep -i 'task-table' || true)
if [ -z "$LOG" ]; then pass "no PHP warnings/errors from the plugin"; else fail "no PHP warnings/errors from the plugin"; echo "$LOG" | sort -u | head -5; fi

echo
echo "$PASSES passed, $FAILS failed"
exit "$FAILS"
