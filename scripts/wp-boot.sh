#!/bin/bash
set -euo pipefail

echo "[wp] Aguardando banco em ${WORDPRESS_DB_HOST}..."

# Garante wp-config alinhado com as env vars do runtime
DB_HOST_ONLY="${WORDPRESS_DB_HOST%%:*}"
DB_PORT_ONLY="${WORDPRESS_DB_HOST##*:}"
if [ "$DB_PORT_ONLY" = "$WORDPRESS_DB_HOST" ]; then
  DB_PORT_ONLY=3306
fi
DB_HOST_FOR_WP="${DB_HOST_ONLY}:${DB_PORT_ONLY}"

wp config create \
  --dbname="${WORDPRESS_DB_NAME}" \
  --dbuser="${WORDPRESS_DB_USER}" \
  --dbpass="${WORDPRESS_DB_PASSWORD}" \
  --dbhost="${DB_HOST_FOR_WP}" \
  --dbcharset=utf8mb4 \
  --force \
  --skip-check \
  --allow-root \
  --path=/var/www/html

wp config set COOKIE_DOMAIN false --raw --type=constant --allow-root --path=/var/www/html
wp config set ADMIN_COOKIE_PATH '/' --type=constant --allow-root --path=/var/www/html
wp config set COOKIEPATH '/' --type=constant --allow-root --path=/var/www/html
wp config set SITECOOKIEPATH '/' --type=constant --allow-root --path=/var/www/html
wp config set FS_METHOD 'direct' --type=constant --allow-root --path=/var/www/html
wp config set WP_POST_REVISIONS 3 --raw --type=constant --allow-root --path=/var/www/html
wp config set AUTOSAVE_INTERVAL 300 --raw --type=constant --allow-root --path=/var/www/html
wp config set WP_MEMORY_LIMIT '256M' --type=constant --allow-root --path=/var/www/html
wp config set WP_MAX_MEMORY_LIMIT '512M' --type=constant --allow-root --path=/var/www/html
wp config set DISALLOW_FILE_EDIT true --raw --type=constant --allow-root --path=/var/www/html
wp config set EMPTY_TRASH_DAYS 7 --raw --type=constant --allow-root --path=/var/www/html
wp config set WP_CACHE true --raw --type=constant --allow-root --path=/var/www/html
wp config set CCD_PAGE_CACHE_TTL 3600 --raw --type=constant --allow-root --path=/var/www/html
wp config set WP_HTTP_BLOCK_EXTERNAL true --raw --type=constant --allow-root --path=/var/www/html
wp config set WP_ACCESSIBLE_HOSTS 'api.wordpress.org,*.wordpress.org,secure.gravatar.com,*.gravatar.com,www.google.com,*.google.com' --type=constant --allow-root --path=/var/www/html

if [ -n "${CCD_RECAPTCHA_SITE_KEY:-}" ]; then
  wp config set CCD_RECAPTCHA_SITE_KEY "${CCD_RECAPTCHA_SITE_KEY}" --type=constant --allow-root --path=/var/www/html
fi
if [ -n "${CCD_RECAPTCHA_SECRET_KEY:-}" ]; then
  wp config set CCD_RECAPTCHA_SECRET_KEY "${CCD_RECAPTCHA_SECRET_KEY}" --type=constant --allow-root --path=/var/www/html
fi

if [ -n "${WP_HOME:-}" ]; then
  wp config set WP_HOME "${WP_HOME}" --type=constant --allow-root --path=/var/www/html
  wp config set WP_SITEURL "${WP_SITEURL:-$WP_HOME}" --type=constant --allow-root --path=/var/www/html
fi

# Railway termina TLS no proxy (ccd-https-proxy detecta X-Forwarded-Proto).
# Nao forcar FORCE_SSL_ADMIN: o healthcheck interno e HTTP e /wp-login.php
# responderia 302, falhando o deploy. O admin publico continua em HTTPS.
wp config set FORCE_SSL_ADMIN false --raw --type=constant --allow-root --path=/var/www/html

db_ready() {
  php -r "
    \$h = getenv('WORDPRESS_DB_HOST') ?: '${DB_HOST_ONLY}';
    \$p = ${DB_PORT_ONLY};
    if (str_contains(\$h, ':')) { [\$h, \$p] = explode(':', \$h, 2); }
    \$m = @new mysqli(\$h, getenv('WORDPRESS_DB_USER'), getenv('WORDPRESS_DB_PASSWORD'), getenv('WORDPRESS_DB_NAME'), (int) \$p);
    if (!\$m || \$m->connect_errno) { fwrite(STDERR, \$m ? \$m->connect_error : 'connect failed'); exit(1); }
    \$m->close();
  "
}

for i in $(seq 1 90); do
  if db_ready; then
    echo "[wp] Banco OK"
    break
  fi
  if [ $((i % 10)) -eq 0 ]; then
    echo "[wp] ainda aguardando ($i/90)"
  fi
  sleep 3
done

if ! db_ready; then
  echo "[wp] ERRO: banco indisponivel apos espera"
  exit 1
fi

SITE_URL="${WP_HOME:-http://localhost:8080}"

if ! wp core is-installed --allow-root --path=/var/www/html >/dev/null 2>&1; then
  echo "[wp] WordPress nao instalado — bootstrap inicial"

  wp core install \
    --url="${SITE_URL}" \
    --title="${WP_TITLE:-Convivendo com Diabetes}" \
    --admin_user="${WP_ADMIN_USER:-admin}" \
    --admin_password="${WP_ADMIN_PASSWORD:-CcdTrocar123!}" \
    --admin_email="${WP_ADMIN_EMAIL:-admin@convivendocomdiabetes.com}" \
    --skip-email \
    --allow-root \
    --path=/var/www/html

  ADMIN_ID="$(wp user get "${WP_ADMIN_USER:-admin}" --field=ID --allow-root --path=/var/www/html)"
  wp user meta update "${ADMIN_ID}" ccd_force_password_change 1 --allow-root --path=/var/www/html
  echo "[wp] Admin criado: user=${WP_ADMIN_USER:-admin} (troca de senha obrigatoria no primeiro acesso)"
else
  echo "[wp] WordPress ja instalado"
fi

# Mantem o tema ativo do dump (empowerwp/mesmerize); so ativa fallback se ele nao existir
CURRENT_THEME="$(wp option get stylesheet --allow-root --path=/var/www/html 2>/dev/null || true)"
if [ -n "$CURRENT_THEME" ] && [ -f "/var/www/html/wp-content/themes/${CURRENT_THEME}/style.css" ]; then
  echo "[wp] Mantendo tema ativo: ${CURRENT_THEME}"
else
  THEME_OK=0
  for theme in empowerwp twentytwentyfive; do
    if [ -d "/var/www/html/wp-content/themes/${theme}" ]; then
      echo "[wp] Tema atual invalido — tentando ${theme}"
      if wp theme activate "${theme}" --allow-root --path=/var/www/html; then
        THEME_OK=1
        break
      fi
    fi
  done
  if [ "$THEME_OK" -ne 1 ]; then
    echo "[wp] AVISO: nenhum tema de fallback disponivel"
  fi
fi

if [ -n "${WP_HOME:-}" ]; then
  TARGET_URL="${WP_SITEURL:-$WP_HOME}"
  CURRENT_URL="$(wp option get siteurl --allow-root --path=/var/www/html 2>/dev/null || true)"
  if [ -n "$CURRENT_URL" ] && [ "$CURRENT_URL" != "$TARGET_URL" ]; then
    echo "[wp] Atualizando URLs ${CURRENT_URL} -> ${TARGET_URL}"
    wp option update home "${WP_HOME}" --allow-root --path=/var/www/html || true
    wp option update siteurl "${TARGET_URL}" --allow-root --path=/var/www/html || true
  else
    echo "[wp] siteurl/home OK (${TARGET_URL})"
  fi

  # search-replace full-table e lento — so roda 1x por WP_HOME (ou FORCE_URL_REWRITE=1)
  # Evita estourar o healthcheck do Railway em todo deploy.
  REWRITE_FOR="$(wp option get ccd_url_rewrite_for --allow-root --path=/var/www/html 2>/dev/null || true)"
  if [ "${FORCE_URL_REWRITE:-0}" = "1" ] || [ "$REWRITE_FOR" != "$WP_HOME" ]; then
    echo "[wp] Removendo URLs do host antigo do banco (uma vez)..."
    wp search-replace "https://www.convivendocomdiabetes.com" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
    wp search-replace "http://www.convivendocomdiabetes.com" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
    wp search-replace "https://convivendocomdiabetes.com" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
    wp search-replace "http://convivendocomdiabetes.com" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
    wp search-replace "http://localhost:8080" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
    wp option update ccd_url_rewrite_for "${WP_HOME}" --allow-root --path=/var/www/html || true
  else
    echo "[wp] URL rewrite ja aplicado para ${WP_HOME}"
  fi
fi

# Volume de uploads: nao fazer chown -R em todo boot (volume grande = minutos)
if [ -d /var/www/html/wp-content/uploads ]; then
  rm -f /var/www/html/wp-content/uploads/.htaccess
  chown www-data:www-data /var/www/html/wp-content/uploads 2>/dev/null || true
  chmod 775 /var/www/html/wp-content/uploads 2>/dev/null || true
fi

# Desativa plugins pesados/ausentes e limpa bloat do WP Statistics (~180MB / 1M linhas)
echo "[wp] Otimizando plugins e banco..."
HEAVY_PLUGINS=(
  wp-statistics
  wp-file-manager
  all-in-one-wp-migration
  wordpress-importer
  regenerate-thumbnails
  phoenix-media-rename
  health-check
  wp-maintenance-mode
  glue-for-yoast-seo-amp
  elementor
  wordfence
  amp
  contact-form-7
  # Front pesado / pouco essencial no request
  google-publisher
  insta-gallery
  auto-post-thumbnail-pro
  sucuri-scanner
)
for plugin in "${HEAVY_PLUGINS[@]}"; do
  wp plugin deactivate "${plugin}" --allow-root --path=/var/www/html >/dev/null 2>&1 || true
done

# Libera espaço no MySQL se as tabelas do Statistics ainda existirem
for table in wp_statistics_visitor wp_statistics_pages wp_statistics_search wp_statistics_exclusions wp_statistics_useronline; do
  wp db query "TRUNCATE TABLE \`${table}\`" --allow-root --path=/var/www/html >/dev/null 2>&1 || true
done

# Define ServerName global (supprime AH00558 nos logs do Railway)
APACHE_SERVER_NAME="${RAILWAY_PUBLIC_DOMAIN:-}"
if [ -z "$APACHE_SERVER_NAME" ] && [ -n "${WP_HOME:-}" ]; then
  APACHE_SERVER_NAME="$(printf '%s' "${WP_HOME}" | sed -E 's|^https?://||; s|/.*$||; s|:.*$||')"
fi
APACHE_SERVER_NAME="${APACHE_SERVER_NAME:-localhost}"
printf '%s\n' "ServerName ${APACHE_SERVER_NAME}" > /etc/apache2/conf-available/servername.conf
a2enconf servername >/dev/null 2>&1 || true

# Garante Listen no PORT do Railway e em :80 (dominio publico)
PORT="${PORT:-80}"
# shellcheck disable=SC1091
. /usr/local/bin/bind-apache-ports.sh

echo "[wp] Apache (ServerName=${APACHE_SERVER_NAME} PORT=${PORT})"

# Pre-aquece a home no cache assim que o Apache aceitar conexoes
(
  warm_host="${APACHE_SERVER_NAME}"
  for _i in 1 2 3 4 5 6 7 8 9 10 12 15; do
    if curl -fsS -o /dev/null -H "Host: ${warm_host}" "http://127.0.0.1:${PORT}/" 2>/dev/null; then
      curl -fsS -o /dev/null -H "Host: ${warm_host}" "http://127.0.0.1:${PORT}/wp-login.php" 2>/dev/null || true
      echo "[wp] cache/health aquecido"
      break
    fi
    sleep 1
  done
) >/tmp/ccd-cache-warm.log 2>&1 &

exec apache2-foreground
