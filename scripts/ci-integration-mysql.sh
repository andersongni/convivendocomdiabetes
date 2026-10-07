#!/usr/bin/env bash
# Integração CI: imagem de produção + MySQL fresco (install via wp-boot).
# Pré-requisito: imagem Docker tagueada como convivendocomdiabetes:ci (load local).
set -euo pipefail

IMAGE="${CCD_CI_IMAGE:-convivendocomdiabetes:ci}"
NET="${CCD_CI_NET:-ccd-ci-net}"
MYSQL_NAME="${CCD_CI_MYSQL:-ccd-ci-mysql}"
REDIS_NAME="${CCD_CI_REDIS:-ccd-ci-redis}"
WP_NAME="${CCD_CI_WP:-ccd-ci-wp}"
HOST_PORT="${CCD_CI_PORT:-18081}"

MYSQL_DB=wordpress
MYSQL_USER=wpci
MYSQL_PASS='WpCiPass123!'
REDIS_PASS='CiRedisPass123!'
WP_ADMIN_USER=ciadmin
WP_ADMIN_PASS='CiAdminPass123!'

WP_BAD="${WP_NAME}-noredisauth"

cleanup() {
  docker rm -f "${WP_NAME}" "${WP_BAD}" "${MYSQL_NAME}" "${REDIS_NAME}" >/dev/null 2>&1 || true
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

echo "[ci] Redis (requirepass — regressao NOAUTH)..."
docker run -d --name "${REDIS_NAME}" --network "${NET}" \
  redis:7-alpine \
  redis-server --requirepass "${REDIS_PASS}"

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

echo "[ci] WordPress (prod entrypoint + Redis auth)..."
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
  -e WP_REDIS_HOST="${REDIS_NAME}" \
  -e WP_REDIS_PORT=6379 \
  -e WP_REDIS_PASSWORD="${REDIS_PASS}" \
  -e WP_REDIS_PREFIX=ccdci_ \
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
  code=$(curl -sS -D /tmp/ci-login.hdr -o /tmp/ci-login.html -w '%{http_code}' --max-time 10 \
    "${BASE}/login" || echo 000)
  if [ "${code}" = "200" ] && grep -Eqi 'loginform|name="log"|wp-submit' /tmp/ci-login.html; then
    echo "[ci] /login => ${code}"
    ok=1
    break
  fi
  if [ $((i % 10)) -eq 0 ]; then
    loc=$(tr -d '\r' </tmp/ci-login.hdr | awk 'tolower($1)=="location:"{print $2; exit}')
    echo "[ci] login attempt ${i}: HTTP ${code} Location=${loc:-}"
    docker logs "${WP_NAME}" 2>&1 | tail -n 30 || true
  fi
  sleep 2
done
if [ "${ok}" != "1" ]; then
  echo "[ci] FAIL /login"
  echo "---- headers ----"
  cat /tmp/ci-login.hdr 2>/dev/null || true
  echo "---- body (head) ----"
  head -c 500 /tmp/ci-login.html 2>/dev/null || true
  echo
  echo "---- wp logs ----"
  docker logs "${WP_NAME}" 2>&1 | tail -n 80 || true
  exit 1
fi

echo "[ci] www Host → 301 apex (Apache/.htaccess)..."
headers=$(curl -sS -D - -o /dev/null --max-time 10 \
  -H "Host: www.convivendocomdiabetes.com" "${BASE}/")
echo "${headers}" | head -n 15
if ! echo "${headers}" | grep -qiE '^HTTP/1\.[01] 301'; then
  echo "[ci] FAIL: esperado 301 para Host www"
  exit 1
fi
if ! echo "${headers}" | grep -qiE '^Location:[[:space:]]*https://convivendocomdiabetes.com/?'; then
  echo "[ci] FAIL: Location deveria ser https://convivendocomdiabetes.com/"
  exit 1
fi

echo "[ci] /ccdhealth com Host www nao redireciona..."
code=$(curl -sS -o /tmp/ci-health-www.body -w '%{http_code}' --max-time 10 \
  -H "Host: www.convivendocomdiabetes.com" "${BASE}/ccdhealth")
test "${code}" = "200"
tr -d '\r' </tmp/ci-health-www.body | grep -qi 'ok'

echo "[ci] Redis object-cache ativado (auth OK)..."
ok=0
for i in $(seq 1 30); do
  if docker exec "${WP_NAME}" test -f /var/www/html/wp-content/object-cache.php 2>/dev/null \
    && docker logs "${WP_NAME}" 2>&1 | grep -q 'Redis OK'; then
    echo "[ci] object-cache.php + Redis OK"
    ok=1
    break
  fi
  sleep 2
done
if [ "${ok}" != "1" ]; then
  echo "[ci] FAIL: Redis object-cache nao ativou"
  docker logs "${WP_NAME}" 2>&1 | grep -E 'Redis|object-cache|NOAUTH' | tail -n 40 || true
  docker logs "${WP_NAME}" 2>&1 | tail -n 60 || true
  exit 1
fi

echo "[ci] Regressao: Redis requirepass SEM senha nao pode derrubar /ccdhealth..."
# DB separado para nao brigar com o WP ja instalado.
docker exec "${MYSQL_NAME}" mysql -uroot -prootpass -e "CREATE DATABASE IF NOT EXISTS wordpress_bad; GRANT ALL ON wordpress_bad.* TO '${MYSQL_USER}'@'%';" >/dev/null
docker run -d --name "${WP_BAD}" --network "${NET}" -p 18082:80 \
  -e WORDPRESS_DB_HOST="${MYSQL_NAME}:3306" \
  -e WORDPRESS_DB_USER="${MYSQL_USER}" \
  -e WORDPRESS_DB_PASSWORD="${MYSQL_PASS}" \
  -e WORDPRESS_DB_NAME=wordpress_bad \
  -e WP_HOME="http://127.0.0.1:18082" \
  -e WP_SITEURL="http://127.0.0.1:18082" \
  -e WP_ADMIN_USER="${WP_ADMIN_USER}" \
  -e WP_ADMIN_PASSWORD="${WP_ADMIN_PASS}" \
  -e WP_ADMIN_EMAIL=ci2@example.com \
  -e WP_TITLE="CCD CI bad redis" \
  -e WP_REDIS_HOST="${REDIS_NAME}" \
  -e WP_REDIS_PORT=6379 \
  "${IMAGE}"

ok=0
for i in $(seq 1 90); do
  code=$(curl -sS -o /tmp/ci-health-bad.body -w '%{http_code}' --max-time 5 \
    "http://127.0.0.1:18082/ccdhealth" || echo 000)
  if [ "${code}" = "200" ]; then
    echo "[ci] /ccdhealth (redis sem senha) => 200"
    ok=1
    break
  fi
  sleep 2
done
if [ "${ok}" != "1" ]; then
  echo "[ci] FAIL: site caiu com Redis mal configurado"
  docker logs "${WP_BAD}" 2>&1 | tail -n 80 || true
  exit 1
fi

echo "[ci] OK integration MySQL+Redis"
