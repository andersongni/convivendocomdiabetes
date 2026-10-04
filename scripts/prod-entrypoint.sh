#!/bin/bash
set -euo pipefail

# --- Railway MySQL plugin vars (se ainda usar DB gerenciado) ---
if [ -z "${WORDPRESS_DB_HOST:-}" ] && [ -n "${MYSQLHOST:-}" ]; then
  export WORDPRESS_DB_HOST="${MYSQLHOST}:${MYSQLPORT:-3306}"
fi
if [ -z "${WORDPRESS_DB_USER:-}" ] && [ -n "${MYSQLUSER:-}" ]; then
  export WORDPRESS_DB_USER="${MYSQLUSER}"
fi
if [ -z "${WORDPRESS_DB_PASSWORD:-}" ] && [ -n "${MYSQLPASSWORD:-}" ]; then
  export WORDPRESS_DB_PASSWORD="${MYSQLPASSWORD}"
fi
if [ -z "${WORDPRESS_DB_NAME:-}" ] && [ -n "${MYSQLDATABASE:-}" ]; then
  export WORDPRESS_DB_NAME="${MYSQLDATABASE}"
fi

# Defaults do compose Railway
export WORDPRESS_DB_HOST="${WORDPRESS_DB_HOST:-db:3306}"
export WORDPRESS_DB_USER="${WORDPRESS_DB_USER:-wpapp}"
export WORDPRESS_DB_PASSWORD="${WORDPRESS_DB_PASSWORD:-changeme-wpapp}"
export WORDPRESS_DB_NAME="${WORDPRESS_DB_NAME:-wordpress}"

# URLs publicas
if [ -z "${WP_HOME:-}" ] && [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
  export WP_HOME="https://${RAILWAY_PUBLIC_DOMAIN}"
fi
if [ -z "${WP_SITEURL:-}" ] && [ -n "${WP_HOME:-}" ]; then
  export WP_SITEURL="${WP_HOME}"
fi

echo "[wp] DB host=${WORDPRESS_DB_HOST} user=${WORDPRESS_DB_USER} name=${WORDPRESS_DB_NAME}"
echo "[wp] HOME=${WP_HOME:-"(ainda nao definida)"}"

EXTRA=""
if [ -n "${WP_HOME:-}" ]; then
  EXTRA="${EXTRA}
define('WP_HOME', getenv('WP_HOME') ?: '${WP_HOME}');
define('WP_SITEURL', getenv('WP_SITEURL') ?: '${WP_SITEURL}');
"
fi
EXTRA="${EXTRA}
define('FORCE_SSL_ADMIN', false);
define('COOKIE_DOMAIN', false);
define('ADMIN_COOKIE_PATH', '/');
define('COOKIEPATH', '/');
define('SITECOOKIEPATH', '/');
define('FS_METHOD', 'direct');
"
export WORDPRESS_CONFIG_EXTRA="${WORDPRESS_CONFIG_EXTRA:-}${EXTRA}"

a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# docker-entrypoint prepara wp-config e depois roda o comando abaixo
exec docker-entrypoint.sh bash -c '
  set -e
  echo "[wp] Aguardando banco..."
  for i in $(seq 1 90); do
    if wp db check --allow-root --path=/var/www/html >/dev/null 2>&1; then
      echo "[wp] Banco OK"
      break
    fi
    sleep 3
  done

  if [ -n "${WP_HOME:-}" ]; then
    echo "[wp] Atualizando siteurl/home -> ${WP_HOME}"
    wp option update home "${WP_HOME}" --allow-root --path=/var/www/html || true
    wp option update siteurl "${WP_SITEURL:-$WP_HOME}" --allow-root --path=/var/www/html || true
  fi

  # Sync de dominio antigo do dump -> dominio atual (posts/options serializados básicos)
  if [ -n "${WP_HOME:-}" ]; then
    wp search-replace "https://www.convivendocomdiabetes.com" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
    wp search-replace "http://www.convivendocomdiabetes.com" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
    wp search-replace "https://convivendocomdiabetes.com" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
  fi

  echo "[wp] Iniciando Apache"
  exec apache2-foreground
'
