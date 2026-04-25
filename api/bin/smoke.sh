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
# expect NAME PATTERN OUTPUT: fail loudly instead of silently skipping a check.
expect() {
    if grep -q -- "$2" <<< "$3"; then echo "ok   $1"; else echo "FAIL $1: no '$2' in: ${3:0:300}"; cat "$dir/log"; exit 1; fi
}

expect "token required" 401 "$(curl -s -o /dev/null -w '%{http_code}' "localhost:$port/api/applications")"
expect "create" '"id":1' "$(api -d '{"company":"Acme","position":"PHP Dev","status":"applied"}' "localhost:$port/api/applications")"
expect "move" '"status":"interview"' "$(api -X PATCH -d '{"status":"interview"}' "localhost:$port/api/applications/1/status")"
expect "interview" '"kind":"tech"' "$(api -d '{"scheduled_at":"2030-01-10T15:00:00+03:00","kind":"tech"}' "localhost:$port/api/applications/1/interviews")"
expect "calendar feed" 'BEGIN:VEVENT' "$(curl -fs "localhost:$port/api/interviews.ics?token=smoke")"
expect "stats" '"interview":1' "$(api "localhost:$port/api/stats")"
expect "csv export" 'Acme,"PHP Dev",interview' "$(api "localhost:$port/api/export.csv")"
echo "API smoke test passed"
