#!/usr/bin/env bash
# Upgrade test: install the previous release, create data shaped like a production site, then
# swap in the working-tree code and check that existing subscribers, signups, templates and links
# keep working.
# The previous release is the newest v* tag in HEAD's history, ignoring tags on HEAD itself, so
# a release build where HEAD already carries the new tag tests the upgrade from the one before.
# Runs in its own stack (pod task-table-upgrade, ports 8890/8026; override with UPGRADE_POD,
# UPGRADE_WP_PORT, UPGRADE_MAIL_PORT) and removes it afterwards.
# Usage: dev/tests/upgrade.sh [old-git-ref]     e.g. v1.1.0 to test a longer upgrade path
#        KEEP=1 keeps the stack for inspection
# Needs podman, node_modules (npm ci), a built plugin (npm run build) and the git tags.
set -uo pipefail
cd "$(dirname "$0")/../.."
ROOT="$(pwd)"
NEW_VERSION="$(grep -m1 'Version:' task-table.php | awk '{print $3}')"
OLD_REF="${1:-$(git tag --list 'v*' --merged HEAD --no-contains HEAD --sort=-v:refname | head -1)}"
[ -n "$OLD_REF" ] || { echo "no previous release tag found (fetch the tags, or pass a ref)"; exit 1; }
# Highest DB schema version the new code migrates to.
NEW_DB="$(grep -o "task_table_db_version', '[0-9.]*'" src/database.php | grep -o "[0-9.]*'$" | tr -d "'" | sort -V | tail -1)"
# DB 1.7 cleans backslashes that releases before it stored in email templates.
needs_unslash_migration() { [ "$(printf '%s\n' "$OLD_DB" 1.7 | sort -V | head -1)" != 1.7 ]; }
export POD="${UPGRADE_POD:-task-table-upgrade}" WP_PORT="${UPGRADE_WP_PORT:-8890}" MAIL_PORT="${UPGRADE_MAIL_PORT:-8026}"
export WP_IMAGE="${WP_IMAGE:-docker.io/library/wordpress:php8.4-apache}"   # production runs PHP 8.4
ENGINE="${ENGINE:-podman}"
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
# A tag's plugin header can lag behind the tag (v1.1.0 said 0.1.0), so trust the tag name.
case "$OLD_REF" in v[0-9]*) sed -i "s/^\( \* Version:           \).*/\1${OLD_REF#v}/" "$OLD/task-table.php" ;; esac
OLD_VERSION="$(grep -m1 'Version:' "$OLD/task-table.php" | awk '{print $3}')"
echo "   $OLD_REF ($OLD_VERSION) -> working tree ($NEW_VERSION)"

echo "== Start old version"
dev/down.sh >/dev/null 2>&1
PLUGIN_SRC="$OLD" SKIP_SEED=1 dev/up.sh >"$TMP/up.log" 2>&1 || { tail -5 "$TMP/up.log"; echo "stack failed"; exit 1; }
PLUGIN_SRC="$OLD"
check "old version active" "$OLD_VERSION" "$(wp plugin get task-table --field=version)"
OLD_DB="$(wp option get task_table_db_version)"

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
# Set outside the form (wp-cli, code), so never escaped: the 1.7 migration must leave its lone backslash alone.
wp option update task_table_email_signup_denied_subject 'Absage \o/ {event_title}' >/dev/null
if needs_unslash_migration; then
  check "old code stored escaped quotes (precondition)" 1 "$(wp option get task_table_email_signup_approved_subject | grep -cF '\"')"
fi

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
echo "   old db version: $OLD_DB, new code migrates to: $NEW_DB"
BEFORE=$(sql "SELECT CONCAT((SELECT COUNT(*) FROM wp_task_table_events),'/',(SELECT COUNT(*) FROM wp_task_table_signups),'/',(SELECT COUNT(*) FROM wp_task_table_subscribers),'/',(SELECT COUNT(*) FROM wp_task_table_subscriber_categories),'/',(SELECT COUNT(*) FROM wp_task_table_categories))")
curl -s -X DELETE "$MAILPIT/api/v1/messages" >/dev/null

echo "== Swap in the new code (database kept)"
"$ENGINE" rm -f "${POD}-wp" >/dev/null
PLUGIN_SRC="$ROOT" dev/up.sh >/dev/null 2>&1
PLUGIN_SRC="$ROOT"
wp eval 'file_put_contents(WP_CONTENT_DIR . "/debug.log", "");' >/dev/null
check "new version active" "$NEW_VERSION" "$(wp plugin get task-table --field=version)"
# A 200 is not enough: a query broken by the schema change renders an empty table. Look for the
# seeded event (table block) and the subscribe form (second block).
check "front page renders both blocks" "200 1 1" "$(status "$BASE/task-table/") $(grep -c 'Workshop: Intro to Git' "$TMP/body") $(grep -c 'class="subscriber-email"' "$TMP/body")"
check "db upgraded to $NEW_DB" "$NEW_DB" "$(wp option get task_table_db_version)"
check "data untouched (events/signups/subscribers/sub-cats/categories)" "$BEFORE" "$(sql "SELECT CONCAT((SELECT COUNT(*) FROM wp_task_table_events),'/',(SELECT COUNT(*) FROM wp_task_table_signups),'/',(SELECT COUNT(*) FROM wp_task_table_subscribers),'/',(SELECT COUNT(*) FROM wp_task_table_subscriber_categories),'/',(SELECT COUNT(*) FROM wp_task_table_categories))")"
check "templates have no escaped quotes" 'Zusage für "{event_title}"' "$(wp option get task_table_email_signup_approved_subject)"
check "unescaped templates unchanged (umlauts, lone backslash)" 'Neu: {event_details} Grüße {site_name}|Absage \o/ {event_title}' "$(wp option get task_table_email_new_event_message)|$(wp option get task_table_email_signup_denied_subject)"
check "signup recipients kept" 2 "$(wp option get task_table_signup_notification_recipients --format=json | python3 -c 'import sys,json;print(len(json.load(sys.stdin)))')"

echo "== Existing users and links"
check "confirmation link mailed before the update still works" 200 "$(status "$API/task-table/v1/subscribers/confirm?email=pending%40example.test&token=$PENDING_TOKEN")"
n=$(login admin admin)
check "admin export works" 200 "$(status -b "$TMP/jar-admin" -H "X-WP-Nonce: $n" "$API/task-table/v1/events/export?archived=all")"

echo "== Pending signup approved after the update"
ALEX=$(sql "SELECT id FROM wp_task_table_signups WHERE user_name='Alex'")
APPROVE=$(curl -s -b "$TMP/jar-admin" "$BASE/wp-admin/admin.php?page=task-table-signups" | grep -o "href=\"[^\"]*action=approve[^\"]*signup_id=$ALEX[^\"]*\"" | head -1 | sed 's/^href="//;s/"$//;s/&#038;/\&/g;s/&amp;/\&/g')
check "approve link present in admin" 1 "$([ -n "$APPROVE" ] && echo 1 || echo 0)"
check "approve pending signup (response, stored status)" "200 approved" "$(status -b "$TMP/jar-admin" "$APPROVE") $(sql "SELECT status FROM wp_task_table_signups WHERE id=$ALEX")"
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
LINK=$(echo "$TEXT" | grep -o 'http[^ ]*task_table_subscription=manage[^ ]*' | head -1)
check "pre-existing subscriber can unsubscribe (response, row deleted)" "200 0" "$(status -X POST -d task_table_action=unsubscribe "$LINK") $(sql "SELECT COUNT(*) FROM wp_task_table_subscribers WHERE email='sub1@example.test'")"

echo "== PHP log after upgrade"
LOG=$(wp eval 'echo @file_get_contents(WP_CONTENT_DIR . "/debug.log");' | grep -i 'task-table' || true)
if [ -z "$LOG" ]; then pass "no PHP warnings/errors from the plugin"; else fail "no PHP warnings/errors from the plugin"; echo "$LOG" | sort -u | head -5; fi

echo
echo "$PASSES passed, $FAILS failed"
exit "$FAILS"
