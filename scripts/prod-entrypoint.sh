#!/bin/bash
set -euo pipefail

# Defaults / Railway private DNS (servico "db")
export WORDPRESS_DB_HOST="${WORDPRESS_DB_HOST:-db:3306}"
export WORDPRESS_DB_USER="${WORDPRESS_DB_USER:-wpapp}"
export WORDPRESS_DB_PASSWORD="${WORDPRESS_DB_PASSWORD:-}"
export WORDPRESS_DB_NAME="${WORDPRESS_DB_NAME:-wordpress}"

# Mapeia plugin MySQL gerenciado (se existir)
if [ -z "${WORDPRESS_DB_PASSWORD}" ] && [ -n "${MYSQLPASSWORD:-}" ]; then
  export WORDPRESS_DB_HOST="${MYSQLHOST:-db}:${MYSQLPORT:-3306}"
  export WORDPRESS_DB_USER="${MYSQLUSER:-wpapp}"
  export WORDPRESS_DB_PASSWORD="${MYSQLPASSWORD}"
  export WORDPRESS_DB_NAME="${MYSQLDATABASE:-wordpress}"
fi

if [ -z "${WP_HOME:-}" ] && [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
  export WP_HOME="https://${RAILWAY_PUBLIC_DOMAIN}"
fi
if [ -z "${WP_SITEURL:-}" ] && [ -n "${WP_HOME:-}" ]; then
  export WP_SITEURL="${WP_HOME}"
fi

if [ -z "${WORDPRESS_DB_PASSWORD}" ]; then
  echo "[wp] ERRO: WORDPRESS_DB_PASSWORD vazia"
  exit 1
fi

echo "[wp] DB host=${WORDPRESS_DB_HOST} user=${WORDPRESS_DB_USER} name=${WORDPRESS_DB_NAME}"
echo "[wp] HOME=${WP_HOME:-"(pendente dominio)"}"

EXTRA="
define('FORCE_SSL_ADMIN', false);
define('COOKIE_DOMAIN', false);
define('ADMIN_COOKIE_PATH', '/');
define('COOKIEPATH', '/');
define('SITECOOKIEPATH', '/');
define('FS_METHOD', 'direct');
"
if [ -n "${WP_HOME:-}" ]; then
  EXTRA="${EXTRA}
define('WP_HOME', getenv('WP_HOME') ?: '${WP_HOME}');
define('WP_SITEURL', getenv('WP_SITEURL') ?: '${WP_SITEURL}');
"
fi
export WORDPRESS_CONFIG_EXTRA="${WORDPRESS_CONFIG_EXTRA:-}${EXTRA}"

a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

exec docker-entrypoint.sh bash -c '
  set -e
  echo "[wp] Aguardando banco (wp db check)..."
  for i in $(seq 1 90); do
    if wp db check --allow-root --path=/var/www/html >/dev/null 2>&1; then
      echo "[wp] Banco OK"
      break
    fi
    sleep 3
  done

  if ! wp db check --allow-root --path=/var/www/html >/dev/null 2>&1; then
    echo "[wp] ERRO: banco indisponivel apos espera"
    exit 1
  fi

  if [ -n "${WP_HOME:-}" ]; then
    echo "[wp] Atualizando URLs -> ${WP_HOME}"
    wp option update home "${WP_HOME}" --allow-root --path=/var/www/html || true
    wp option update siteurl "${WP_SITEURL:-$WP_HOME}" --allow-root --path=/var/www/html || true
    wp search-replace "https://www.convivendocomdiabetes.com" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
    wp search-replace "http://www.convivendocomdiabetes.com" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
    wp search-replace "https://convivendocomdiabetes.com" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
  fi

  echo "[wp] Apache"
  exec apache2-foreground
'
