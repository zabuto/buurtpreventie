#!/usr/bin/env bash
# Opens an issue titled $TITLE with the tail of report.txt, or comments on the open issue with that title, so a
# recurring weekly failure stays in one thread.
set -euo pipefail

body="$(printf 'Run: %s/%s/actions/runs/%s\n\n```text\n%s\n```\n' \
  "$GITHUB_SERVER_URL" "$GITHUB_REPOSITORY" "$GITHUB_RUN_ID" "$(tail -n 200 report.txt)")"

number="$(gh issue list --state open --search "in:title \"$TITLE\"" --json number,title \
  --jq "map(select(.title == \"$TITLE\")) | .[0].number // empty")"

if [ -n "$number" ]; then
  gh issue comment "$number" --body "$body"
else
  gh issue create --title "$TITLE" --body "$body"
fi
