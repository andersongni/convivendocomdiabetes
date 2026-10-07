#!/usr/bin/env bash
# Smoke de producao (apex canonico + www → 301).
# Uso: ./scripts/smoke-prod.sh
# Opcional: APEX_BASE=https://convivendocomdiabetes.com
set -euo pipefail

APEX_BASE="${APEX_BASE:-https://convivendocomdiabetes.com}"
APEX_BASE="${APEX_BASE%/}"
WWW_BASE="${WWW_BASE:-https://www.convivendocomdiabetes.com}"
WWW_BASE="${WWW_BASE%/}"

echo "==> Liveness ${APEX_BASE}/ccdhealth"
code=$(curl -sS -o /tmp/ccd-smoke-health.body -w '%{http_code}' --max-time 25 \
  "${APEX_BASE}/ccdhealth")
body=$(tr -d '\r' </tmp/ccd-smoke-health.body | head -c 64)
echo "GET /ccdhealth => ${code} body=${body}"
test "${code}" = "200"
echo "${body}" | grep -qi 'ok'

# Railway healthcheck nao segue 301 — probes no apex nao podem redirecionar.
loc=$(curl -sS -o /dev/null -w '%{redirect_url}' --max-time 15 \
  "${APEX_BASE}/ccdhealth" || true)
if [ -n "${loc}" ]; then
  echo "FAIL: /ccdhealth redirecionou para ${loc}"
  exit 1
fi

echo "==> Readiness ${APEX_BASE}/ccdready"
code=$(curl -sS -o /tmp/ccd-smoke-ready.body -w '%{http_code}' --max-time 25 \
  "${APEX_BASE}/ccdready")
body=$(tr -d '\r' </tmp/ccd-smoke-ready.body | head -c 64)
echo "GET /ccdready => ${code} body=${body}"
test "${code}" = "200"
echo "${body}" | grep -qi 'ok'
loc=$(curl -sS -o /dev/null -w '%{redirect_url}' --max-time 15 \
  "${APEX_BASE}/ccdready" || true)
if [ -n "${loc}" ]; then
  echo "FAIL: /ccdready redirecionou para ${loc}"
  exit 1
fi

echo "==> Home ${APEX_BASE}/"
code=$(curl -sS -o /tmp/ccd-smoke-home.html -w '%{http_code}' --max-time 30 \
  "${APEX_BASE}/")
echo "GET / => ${code}"
test "${code}" = "200"
grep -qi 'html' /tmp/ccd-smoke-home.html

echo "==> Login ${APEX_BASE}/login"
code=$(curl -sS -o /tmp/ccd-smoke-login.html -w '%{http_code}' --max-time 30 \
  "${APEX_BASE}/login")
echo "GET /login => ${code}"
test "${code}" = "200"
grep -Eqi 'loginform|name="log"|wp-submit' /tmp/ccd-smoke-login.html

echo "==> www → apex (301)"
# -L desligado: queremos o 301, nao o follow.
headers=$(curl -sS -D - -o /dev/null --max-time 25 "${WWW_BASE}/")
echo "${headers}" | head -n 20
echo "${headers}" | grep -qiE '^HTTP/2 301|^HTTP/1\.[01] 301'
echo "${headers}" | grep -qiE "^[Ll]ocation:[[:space:]]*${APEX_BASE}/?"

echo "OK smoke production"
