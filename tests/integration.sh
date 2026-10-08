#!/usr/bin/env bash
# End-to-end test against the docker compose stack (app + Mailpit).
# Usage: docker compose up -d --build --wait && tests/integration.sh
set -euo pipefail

APP=${APP_URL:-http://127.0.0.1:8080}
MAILPIT=${MAILPIT_URL:-http://127.0.0.1:8025}
JAR=$(mktemp)
trap 'rm -f "$JAR"' EXIT

pass() { printf 'ok   %s\n' "$1"; }
fail() { printf 'FAIL %s\n' "$1" >&2; exit 1; }

count_subject() {
  curl -fsS -G "$MAILPIT/api/v1/search" --data-urlencode "query=subject:\"$1\"" | jq -r '.messages_count'
}

curl -fsS -X DELETE "$MAILPIT/api/v1/messages" >/dev/null

# 1. CLI inside the container
printf 'Hello from the CLI integration test.\n' |
  docker compose exec -T app php bin/send-mail --to ops@example.test --subject 'CLI integration' --json |
  jq -e '.status == "sent"' >/dev/null || fail "CLI send"
[ "$(count_subject 'CLI integration')" = 1 ] || fail "CLI mail not in Mailpit"
pass "CLI sends via SMTP to Mailpit"

# 2. Security headers on the form page
headers=$(curl -fsS -D - -o /dev/null -c "$JAR" "$APP/")
grep -qi "^content-security-policy: default-src 'none'" <<<"$headers" || fail "CSP header"
grep -qi '^x-frame-options: DENY' <<<"$headers" || fail "X-Frame-Options"
grep -qi 'set-cookie: contact_sid=.*httponly.*samesite=strict' <<<"$headers" || fail "session cookie flags"
if grep -qi '^x-powered-by' <<<"$headers"; then fail "X-Powered-By leaked"; fi
pass "security headers and hardened session cookie"

token=$(curl -fsS -b "$JAR" -c "$JAR" "$APP/" | sed -n 's/.*name="csrf_token" value="\([0-9a-f]\{64\}\)".*/\1/p')
[ -n "$token" ] || fail "CSRF token not found"
sleep 4   # minimum fill time

post() { # post <expected-status> [curl --data-urlencode args...]
  local expected=$1; shift
  local status
  status=$(curl -sS -o /tmp/integration-body.json -w '%{http_code}' -b "$JAR" -c "$JAR" \
    -H 'Accept: application/json' "$@" "$APP/send.php")
  [ "$status" = "$expected" ] || { cat /tmp/integration-body.json >&2; fail "expected HTTP $expected, got $status"; }
}

# 3. Missing CSRF token
post 403 --data-urlencode name=Jane --data-urlencode email=jane@example.com \
  --data-urlencode subject=x --data-urlencode message='hello there, world'
pass "missing CSRF token -> 403"

# 4. Header injection attempt in the e-mail field
post 422 --data-urlencode "csrf_token=$token" --data-urlencode name=Eve \
  --data-urlencode $'email=eve@example.com\r\nBcc: victim@example.net' \
  --data-urlencode subject=Injection --data-urlencode message='trying header injection'
jq -e '.errors.email' /tmp/integration-body.json >/dev/null || fail "email error missing"
pass "header injection -> 422"

# 5. Honeypot: fake success, nothing sent
post 200 --data-urlencode "csrf_token=$token" --data-urlencode name=Bot --data-urlencode email=bot@example.com \
  --data-urlencode subject='Honeypot test' --data-urlencode message='I am a bot filling every field' \
  --data-urlencode website=http://spam.example
[ "$(count_subject 'Honeypot test')" = 0 ] || fail "honeypot submission was sent"
pass "honeypot -> fake 200, no mail"

# The honeypot rotated the token: fetch a fresh one.
token=$(curl -fsS -b "$JAR" -c "$JAR" "$APP/" | sed -n 's/.*name="csrf_token" value="\([0-9a-f]\{64\}\)".*/\1/p')
sleep 4

# 6. Valid submission
post 200 --data-urlencode "csrf_token=$token" --data-urlencode 'name=Jane Doe' \
  --data-urlencode email=jane@example.com --data-urlencode 'subject=Form integration' \
  --data-urlencode 'message=Hello, this is the end-to-end test of the contact form.'
jq -e '.ok == true' /tmp/integration-body.json >/dev/null || fail "form response"
id=$(curl -fsS -G "$MAILPIT/api/v1/search" --data-urlencode 'query=subject:"[Contact] Form integration"' | jq -r '.messages[0].ID')
if [ -z "$id" ] || [ "$id" = null ]; then fail "form mail not in Mailpit"; fi
msg=$(curl -fsS "$MAILPIT/api/v1/message/$id")
jq -e '.From.Address == "noreply@example.test" and .ReplyTo[0].Address == "jane@example.com"
       and .To[0].Address == "inbox@example.test" and ((.Bcc // []) | length) == 0' <<<"$msg" >/dev/null ||
  fail "unexpected envelope: $(jq -c '{From, ReplyTo, To, Bcc}' <<<"$msg")"
pass "valid form submission delivered with fixed From and visitor Reply-To"

# 7. Token is single-use
post 403 --data-urlencode "csrf_token=$token" --data-urlencode 'name=Jane Doe' \
  --data-urlencode email=jane@example.com --data-urlencode 'subject=Replay' \
  --data-urlencode 'message=Replaying the same token must fail.'
pass "CSRF token replay -> 403"

# 8. GET on the endpoint
[ "$(curl -s -o /dev/null -w '%{http_code}' "$APP/send.php")" = 405 ] || fail "GET send.php"
pass "GET send.php -> 405"

echo "integration: all checks passed"
