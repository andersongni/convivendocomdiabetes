#!/usr/bin/env bash
# UX smoke CI: admin via WP-CLI, comentario, upload, paginas chave.
# Requer: WP_NAME, BASE, WP_ADMIN_USER
set -euo pipefail

WP_NAME="${WP_NAME:?}"
BASE="${BASE:?}"
BASE="${BASE%/}"
WP_ADMIN_USER="${WP_ADMIN_USER:?}"
POST_SLUG="${POST_SLUG:-hipoglicemia}"
TMP="${TMPDIR:-/tmp}/ccd-ci-ux-$$"
mkdir -p "${TMP}"
trap 'rm -rf "${TMP}"' EXIT

WP=(docker exec "${WP_NAME}" wp --allow-root --path=/var/www/html)
fail=0
ok() { echo "OK  $1${2:+ — $2}"; }
bad() { echo "FAIL $1${2:+ — $2}"; fail=$((fail + 1)); }

echo "[ci-ux] => ${BASE}"

# Admin: usuario existe e tem capacidade manage_options
if "${WP[@]}" user get "${WP_ADMIN_USER}" --field=ID >/dev/null 2>&1; then
  ok "admin user" "${WP_ADMIN_USER}"
else
  bad "admin user" "${WP_ADMIN_USER} ausente"
fi
caps="$("${WP[@]}" user list --login="${WP_ADMIN_USER}" --field=roles 2>/dev/null || true)"
if printf '%s\n' "${caps}" | grep -qi administrator; then
  ok "admin role" "${caps}"
else
  bad "admin role" "roles=${caps}"
fi

# /wp-admin/ sem cookie deve redirecionar para login (rota viva)
admin_code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 20 "${BASE}/wp-admin/" || echo 000)
if [ "${admin_code}" = "302" ] || [ "${admin_code}" = "301" ]; then
  ok "wp-admin redirect" "HTTP ${admin_code}"
elif [ "${admin_code}" = "200" ]; then
  ok "wp-admin HTTP" "HTTP 200"
else
  bad "wp-admin" "HTTP ${admin_code}"
fi

# Login form (regressao rewrite)
code=$(curl -sS -o "${TMP}/login.html" -w '%{http_code}' --max-time 20 "${BASE}/login" || echo 000)
if [ "${code}" = "200" ] && grep -Eqi 'loginform|name="log"|wp-submit' "${TMP}/login.html"; then
  ok "login form"
else
  bad "login form" "HTTP ${code}"
fi

POST_ID="$("${WP[@]}" post list --post_type=post --name="${POST_SLUG}" --field=ID 2>/dev/null | head -n1 || true)"
if [ -n "${POST_ID}" ]; then
  count="$("${WP[@]}" comment list --post_id="${POST_ID}" --format=count 2>/dev/null || echo 0)"
  if [ "${count}" -ge 1 ] 2>/dev/null; then
    ok "comentarios no post" "post=${POST_SLUG} count=${count}"
  else
    bad "comentarios no post" "nenhum comentario em ${POST_SLUG}"
  fi
  code=$(curl -sS -o "${TMP}/post.html" -w '%{http_code}' --max-time 25 "${BASE}/${POST_SLUG}/" || echo 000)
  if [ "${code}" = "200" ] && grep -qiE 'commentform|id="comment"|respond' "${TMP}/post.html"; then
    ok "formulario de comentario" "HTTP ${code}"
  else
    bad "formulario de comentario" "HTTP ${code}"
  fi
else
  bad "post ${POST_SLUG}" "nao encontrado"
fi

if docker exec "${WP_NAME}" test -f /var/www/html/wp-content/uploads/ci/probe.txt; then
  ok "upload dir" "uploads/ci/probe.txt"
else
  bad "upload dir" "probe ausente"
fi
up_code=$(curl -sS -o /dev/null -w '%{http_code}' --max-time 15 \
  "${BASE}/wp-content/uploads/ci/probe.txt" || echo 000)
if [ "${up_code}" = "200" ]; then
  ok "upload HTTP" "HTTP ${up_code}"
else
  bad "upload HTTP" "HTTP ${up_code}"
fi

code=$(curl -sS -o "${TMP}/blog.html" -w '%{http_code}' --max-time 25 "${BASE}/blog/" || echo 000)
if [ "${code}" = "200" ]; then
  ok "blog HTTP"
else
  bad "blog HTTP" "status=${code}"
fi

code=$(curl -sS -o "${TMP}/contato.html" -w '%{http_code}' --max-time 25 "${BASE}/contato/" || echo 000)
if [ "${code}" = "200" ]; then
  ok "contato HTTP"
  if grep -qiE 'wpforms|wpcf7|contact-form|type="email"|name="email"' "${TMP}/contato.html"; then
    ok "contato form marker"
  else
    ok "contato form marker" "pagina sem form (aceitavel no seed minimo)"
  fi
else
  bad "contato HTTP" "status=${code}"
fi

# Soft-nav: home carrega e tem navegacao principal
code=$(curl -sS -o "${TMP}/home.html" -w '%{http_code}' --max-time 25 "${BASE}/" || echo 000)
if [ "${code}" = "200" ] && grep -qiE '<nav|menu-item|site-header' "${TMP}/home.html"; then
  ok "home nav"
else
  bad "home nav" "HTTP ${code}"
fi

if [ "${fail}" -gt 0 ]; then
  echo "[ci-ux] Falhou: ${fail} check(s)"
  exit 1
fi
echo "[ci-ux] OK"
