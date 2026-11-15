#!/usr/bin/env bash
# Check that the files are ready for release <version> and print its CHANGELOG.md section
# (the release notes). Run it before tagging; the release workflow runs it on the tag.
# Usage: dev/release-check.sh 1.3.0      (a leading v is accepted: v1.3.0)
set -euo pipefail
cd "$(dirname "$0")/.."
V="${1:?usage: dev/release-check.sh <version>}"; V="${V#v}"
ERR=0
err() { echo "release-check: $1" >&2; ERR=1; }
same() { [ "$2" = "$V" ] || err "$1 says '$2', expected '$V'"; }

[[ "$V" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]] || err "'$V' is not a semantic version"
same "task-table.php Version" "$(grep -m1 '^ \* Version:' task-table.php | awk '{print $3}')"
same "readme.txt Stable tag" "$(grep -m1 '^Stable tag:' readme.txt | awk '{print $3}')"
same "package.json version" "$(python3 -c 'import json;print(json.load(open("package.json"))["version"])')"
grep -qxF "= $V =" readme.txt || err "readme.txt has no '= $V =' changelog entry"

NOTES="$(awk -v h="## [$V]" 'index($0, h) == 1 { on = 1; next } on && /^## \[/ { exit } on' CHANGELOG.md)"
[ -n "$(echo "$NOTES" | tr -d '[:space:]')" ] || err "CHANGELOG.md has no '## [$V]' section"

[ "$ERR" = 0 ] || exit 1
# Trim leading and trailing blank lines.
echo "$NOTES" | sed -e '/./,$!d' | tac | sed -e '/./,$!d' | tac
