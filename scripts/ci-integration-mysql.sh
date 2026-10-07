#!/usr/bin/env bash
# Integração CI: imagem de produção + MySQL fresco (install via wp-boot).
# Pré-requisito: imagem Docker tagueada como convivendocomdiabetes:ci (load local).
set -euo pipefail

IMAGE="${CCD_CI_IMAGE:-convivendocomdiabetes:ci}"
NET="${CCD_CI_NET:-ccd-ci-net}"
MYSQL_NAME="${CCD_CI_MYSQL:-ccd-ci-mysql}"
WP_NAME="${CCD_CI_WP:-ccd-ci-wp}"
HOST_PORT="${CCD_CI_PORT:-18081}"

MYSQL_DB=wordpress
MYSQL_USER=wpci
MYSQL_PASS='WpCiPass123!'
WP_ADMIN_USER=ciadmin
WP_ADMIN_PASS='CiAdminPass123!'

cleanup() {
  docker rm -f "${WP_NAME}" "${MYSQL_NAME}" >/dev/null 2>&1 || true
  docker network rm "${NET}" >/dev/null 2>&1 || true
}
trap cleanup EXIT

cleanup
docker network create "${NET}"

echo "[ci] MySQL..."
docker run -d --name "${MYSQL_NAME}" --network "${NET}" \
  -e MYSQL_DATABASE="${MYSQL_DB}" \
  -e MYSQL_USER="${MYSQL_USER}" \
  -e MYSQL_PASSWORD="${MYSQL_PASS}" \
  -e MYSQL_ROOT_PASSWORD=rootpass \
  mysql:8.0 \
  --max_allowed_packet=64M

echo "[ci] Aguardando MySQL..."
for i in $(seq 1 60); do
  if docker exec "${MYSQL_NAME}" mysqladmin ping -h 127.0.0.1 -uroot -prootpass --silent 2>/dev/null; then
    echo "[ci] MySQL OK (${i}s)"
    break
  fi
  if [ "$i" -eq 60 ]; then
    echo "[ci] MySQL nao ficou pronto"
    docker logs "${MYSQL_NAME}" 2>&1 | tail -n 80 || true
    exit 1
  fi
  sleep 2
done

echo "[ci] WordPress (prod entrypoint)..."
docker run -d --name "${WP_NAME}" --network "${NET}" -p "${HOST_PORT}:80" \
  -e WORDPRESS_DB_HOST="${MYSQL_NAME}:3306" \
  -e WORDPRESS_DB_USER="${MYSQL_USER}" \
  -e WORDPRESS_DB_PASSWORD="${MYSQL_PASS}" \
  -e WORDPRESS_DB_NAME="${MYSQL_DB}" \
  -e WP_HOME="http://127.0.0.1:${HOST_PORT}" \
  -e WP_SITEURL="http://127.0.0.1:${HOST_PORT}" \
  -e WP_ADMIN_USER="${WP_ADMIN_USER}" \
  -e WP_ADMIN_PASSWORD="${WP_ADMIN_PASS}" \
  -e WP_ADMIN_EMAIL=ci@example.com \
  -e WP_TITLE="CCD CI" \
  "${IMAGE}"

BASE="http://127.0.0.1:${HOST_PORT}"

echo "[ci] Aguardando /ccdhealth..."
ok=0
for i in $(seq 1 90); do
  code=$(curl -sS -o /tmp/ci-health.body -w '%{http_code}' --max-time 5 \
    "${BASE}/ccdhealth" || echo 000)
  if [ "${code}" = "200" ]; then
    body=$(tr -d '\r' </tmp/ci-health.body | head -c 64)
    echo "[ci] /ccdhealth => ${code} body=${body}"
    echo "${body}" | grep -qi 'ok'
    ok=1
    break
  fi
  if [ $((i % 10)) -eq 0 ]; then
    echo "[ci] attempt ${i}: HTTP ${code}"
    docker logs "${WP_NAME}" 2>&1 | tail -n 20 || true
  fi
  sleep 2
done
[ "${ok}" = "1" ]

echo "[ci] /login (boot + rewrite)..."
ok=0
for i in $(seq 1 60); do
  code=$(curl -sS -o /tmp/ci-login.html -w '%{http_code}' --max-time 10 \
    "${BASE}/login" || echo 000)
  if [ "${code}" = "200" ] && grep -Eqi 'loginform|name="log"|wp-submit' /tmp/ci-login.html; then
    echo "[ci] /login => ${code}"
    ok=1
    break
  fi
  if [ $((i % 10)) -eq 0 ]; then
    echo "[ci] login attempt ${i}: HTTP ${code}"
  fi
  sleep 2
done
[ "${ok}" = "1" ]

echo "[ci] www Host → 301 apex (Apache)..."
headers=$(curl -sS -D - -o /dev/null --max-time 10 \
  -H "Host: www.convivendocomdiabetes.com" "${BASE}/")
echo "${headers}" | head -n 15
echo "${headers}" | grep -qiE '^HTTP/1\.[01] 301'
echo "${headers}" | grep -qiE '^Location:[[:space:]]*https://convivendocomdiabetes.com/?'

echo "[ci] /ccdhealth com Host www nao redireciona..."
code=$(curl -sS -o /tmp/ci-health-www.body -w '%{http_code}' --max-time 10 \
  -H "Host: www.convivendocomdiabetes.com" "${BASE}/ccdhealth")
test "${code}" = "200"
tr -d '\r' </tmp/ci-health-www.body | grep -qi 'ok'

echo "[ci] OK integration MySQL"
