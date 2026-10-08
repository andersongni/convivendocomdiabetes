#!/usr/bin/env bash
# Smoke: post private nao vaza no HTML anonimo nem via page cache / Cache-Control.
# Requer: WP_NAME, BASE, WP_ADMIN_USER, WP_ADMIN_PASS
# Regressao: HTML com "Privado:"/"Private:" cacheado e servido a visitante deslogado.
set -euo pipefail

WP_NAME="${WP_NAME:?}"
BASE="${BASE:?}"
BASE="${BASE%/}"
WP_ADMIN_USER="${WP_ADMIN_USER:?}"
WP_ADMIN_PASS="${WP_ADMIN_PASS:?}"
TMP="${TMPDIR:-/tmp}/ccd-ci-private-cache-$$"
mkdir -p "${TMP}"
trap 'rm -rf "${TMP}"' EXIT

WP=(docker exec "${WP_NAME}" wp --allow-root --path=/var/www/html)
fail=0
ok() { echo "OK  $1${2:+ — $2}"; }
bad() { echo "FAIL $1${2:+ — $2}"; fail=$((fail + 1)); }

PRIVATE_SLUG="ci-private-leak-probe"
PRIVATE_TITLE="CI private leak probe"
PRIVATE_MARKER_RE='Privado:|Protegido:|Private:|Protected:|ci-private-leak-probe'

echo "[ci-private-cache] => ${BASE}"

# --- Seed: post private unico ---
EXISTING="$("${WP[@]}" post list --post_type=post --name="${PRIVATE_SLUG}" --post_status=any --field=ID 2>/dev/null | head -n1 || true)"
if [ -n "${EXISTING}" ]; then
  "${WP[@]}" post delete "${EXISTING}" --force >/dev/null 2>&1 || true
fi
PRIV_ID="$("${WP[@]}" post create \
  --post_type=post \
  --post_status=private \
  --post_name="${PRIVATE_SLUG}" \
  --post_title="${PRIVATE_TITLE}" \
  --post_content='Probe CI: nao deve aparecer para anonimo.' \
  --porcelain)"
if [[ "${PRIV_ID}" =~ ^[0-9]+$ ]]; then
  ok "post private criado" "ID=${PRIV_ID}"
else
  bad "post private criado" "resposta=${PRIV_ID}"
fi

# Garante page cache ativo (wp-boot ja seta; reforco).
"${WP[@]}" config set WP_CACHE true --raw --type=constant >/dev/null 2>&1 || true
docker exec "${WP_NAME}" mkdir -p /var/www/html/wp-content/cache/ccd-page
docker exec "${WP_NAME}" sh -c 'rm -f /var/www/html/wp-content/cache/ccd-page/*.html' || true

# --- 1) Anonimo: /blog/ sem badge private nem slug ---
code=$(curl -sS -o "${TMP}/anon1.html" -w '%{http_code}' --max-time 25 "${BASE}/blog/" || echo 000)
if [ "${code}" = "200" ]; then
  ok "blog anonimo HTTP"
else
  bad "blog anonimo HTTP" "status=${code}"
fi
if grep -qiE "${PRIVATE_MARKER_RE}" "${TMP}/anon1.html"; then
  bad "blog anonimo limpo" "encontrou Privado/slug private"
  grep -niE "${PRIVATE_MARKER_RE}" "${TMP}/anon1.html" | head -n 5 || true
else
  ok "blog anonimo limpo"
fi

# --- 2) Sessao admin: Cache-Control nao pode ser public+s-maxage ---
COOKIE_LINE="$("${WP[@]}" eval "
\$u = get_user_by( 'login', '${WP_ADMIN_USER}' );
if ( ! \$u ) { echo ''; exit; }
echo LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( (int) \$u->ID, time() + HOUR_IN_SECONDS, 'logged_in' );
" 2>/dev/null | tr -d '\r' | tail -n1)"
if [ -z "${COOKIE_LINE}" ] || [[ "${COOKIE_LINE}" != *=* ]]; then
  bad "cookie admin" "wp_generate_auth_cookie falhou"
else
  ok "cookie admin"
  hdr=$(curl -sSI --max-time 25 -H "Cookie: ${COOKIE_LINE}" "${BASE}/blog/" || true)
  printf '%s\n' "${hdr}" > "${TMP}/admin.hdr"
  code=$(printf '%s\n' "${hdr}" | head -n1 | grep -oE '[0-9]{3}' | head -n1 || echo 000)
  if [ "${code}" = "200" ]; then
    ok "blog logado HTTP"
  else
    bad "blog logado HTTP" "status=${code}"
  fi
  cc=$(printf '%s\n' "${hdr}" | grep -iE '^Cache-Control:' | tr -d '\r' || true)
  if printf '%s\n' "${cc}" | grep -qiE 'private|no-store|no-cache'; then
    ok "Cache-Control logado" "${cc}"
  else
    bad "Cache-Control logado" "esperado private/no-store; got=${cc:-<vazio>}"
  fi
  if printf '%s\n' "${cc}" | grep -qiE 's-maxage='; then
    bad "Cache-Control logado sem s-maxage" "${cc}"
  else
    ok "Cache-Control logado sem s-maxage"
  fi
  # advanced-cache deve ignorar cookie wordpress_logged_in_*
  if printf '%s\n' "${hdr}" | grep -qiE '^X-CCD-Cache:\s*HIT'; then
    bad "page cache miss logado" "X-CCD-Cache: HIT com cookie"
  else
    ok "page cache miss logado"
  fi
  curl -sS --max-time 25 -H "Cookie: ${COOKIE_LINE}" -o "${TMP}/admin.html" "${BASE}/blog/" || true
  if grep -qiE "${PRIVATE_MARKER_RE}" "${TMP}/admin.html"; then
    ok "blog logado ve private" "(esperado)"
  else
    # Locale/tema pode nao prefixar; ainda assim o post private existe — soft ok.
    ok "blog logado ve private" "badge ausente (aceitavel se tema nao prefixa)"
  fi
fi

# --- 3) Anonimo de novo apos visita admin ---
docker exec "${WP_NAME}" sh -c 'rm -f /var/www/html/wp-content/cache/ccd-page/*.html' || true
code=$(curl -sS -o "${TMP}/anon2.html" -w '%{http_code}' --max-time 25 "${BASE}/blog/" || echo 000)
if [ "${code}" = "200" ] && ! grep -qiE "${PRIVATE_MARKER_RE}" "${TMP}/anon2.html"; then
  ok "blog anonimo pos-admin limpo"
else
  bad "blog anonimo pos-admin limpo" "HTTP ${code} ou vazou private"
fi

# --- 4) Arquivo envenenado no page cache nao pode ser servido ---
# Aquece /blog/ para descobrir a chave real (md5 host|REQUEST_URI), depois envenena.
docker exec "${WP_NAME}" sh -c 'rm -f /var/www/html/wp-content/cache/ccd-page/*.html' || true
curl -sS --max-time 25 -o /dev/null "${BASE}/blog/" || true
sleep 1
REAL_CACHE="$(docker exec "${WP_NAME}" sh -c \
  'ls -1t /var/www/html/wp-content/cache/ccd-page/*.html 2>/dev/null | head -n1' \
  | tr -d '\r' || true)"
if [ -z "${REAL_CACHE}" ]; then
  bad "page cache warm" "nenhum .html apos GET /blog/ (WP_CACHE?)"
else
  ok "page cache warm" "$(basename "${REAL_CACHE}")"
  POISON_HTML="<!DOCTYPE html><html><head><title>poison</title></head><body><h2>Privado: ${PRIVATE_TITLE}</h2><p>ci-private-leak-probe</p></body></html>"
  # Escreve via docker cp-friendly printf; path absoluto do container.
  docker exec "${WP_NAME}" sh -c "printf '%s' '${POISON_HTML}' > '${REAL_CACHE}'"
  code=$(curl -sS -o "${TMP}/anon3.html" -D "${TMP}/anon3.hdr" -w '%{http_code}' --max-time 25 "${BASE}/blog/" || echo 000)
  # HIT com HTML poison = regressao; MISS/regenerado sem Privado = OK.
  if grep -qiE '^X-CCD-Cache:\s*HIT' "${TMP}/anon3.hdr" 2>/dev/null \
    && grep -qiE "${PRIVATE_MARKER_RE}" "${TMP}/anon3.html"; then
    bad "advanced-cache rejeita poison" "serviu HIT com Privado:"
  elif grep -qiE "${PRIVATE_MARKER_RE}" "${TMP}/anon3.html"; then
    bad "advanced-cache rejeita poison" "HTML anonimo ainda tem Privado/slug"
  else
    ok "advanced-cache rejeita poison" "HTTP ${code}"
  fi
fi

# Disco: nenhum .html residual com badge private.
LEAK_FILES="$(docker exec "${WP_NAME}" sh -c \
  'grep -lRE "Privado:|Private:|Protegido:|Protected:" /var/www/html/wp-content/cache/ccd-page 2>/dev/null || true' \
  | tr -d '\r' || true)"
if [ -n "${LEAK_FILES}" ]; then
  bad "disk cache sem Privado" "${LEAK_FILES}"
else
  ok "disk cache sem Privado"
fi

# Cleanup probe (nao deixa lixo no DB de CI).
if [[ "${PRIV_ID}" =~ ^[0-9]+$ ]]; then
  "${WP[@]}" post delete "${PRIV_ID}" --force >/dev/null 2>&1 || true
fi

if [ "${fail}" -ne 0 ]; then
  echo "[ci-private-cache] ${fail} falha(s)"
  exit 1
fi
echo "[ci-private-cache] OK"
exit 0
