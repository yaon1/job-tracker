#!/usr/bin/env bash
# Starts the API with the built-in server on a temporary database and walks through
# the main flows over real HTTP. Used in CI next to the unit tests.
set -euo pipefail
cd "$(dirname "$0")/.."
dir=$(mktemp -d)
port=${PORT:-8790}
API_TOKEN=smoke DATABASE_PATH="$dir/db.sqlite" php -S "127.0.0.1:$port" -t public >"$dir/log" 2>&1 &
pid=$!
trap 'kill $pid 2>/dev/null; rm -rf "$dir"' EXIT
for _ in $(seq 20); do curl -fs "localhost:$port/health" >/dev/null && break; sleep 0.25; done

api() { curl -fsS -H 'Authorization: Bearer smoke' -H 'Content-Type: application/json' "$@"; }
ok() { echo "ok   $1"; }

[[ $(curl -s -o /dev/null -w '%{http_code}' "localhost:$port/api/applications") == 401 ]] && ok "token required"
api -d '{"company":"Acme","position":"PHP Dev","status":"applied"}' "localhost:$port/api/applications" >/dev/null && ok "create"
api -X PATCH -d '{"status":"interview"}' "localhost:$port/api/applications/1/status" | grep -q '"status":"interview"' && ok "move"
api -d '{"scheduled_at":"2030-01-10T15:00:00+03:00","kind":"tech"}' "localhost:$port/api/applications/1/interviews" >/dev/null && ok "interview"
curl -fs "localhost:$port/api/interviews.ics?token=smoke" | grep -q 'BEGIN:VEVENT' && ok "calendar feed"
api "localhost:$port/api/stats" | grep -q '"interview":1' && ok "stats"
api "localhost:$port/api/export.csv" | grep -q 'Acme,PHP Dev,interview' && ok "csv export"
echo "API smoke test passed"
