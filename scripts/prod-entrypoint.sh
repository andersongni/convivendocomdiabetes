#!/bin/bash
set -euo pipefail

# Mapeia variaveis do plugin MySQL do Railway -> WordPress Docker
# Ref: https://docs.railway.com/databases/mysql
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

# URLs publicas (Railway injeta RAILWAY_PUBLIC_DOMAIN)
if [ -z "${WP_HOME:-}" ] && [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
  export WP_HOME="https://${RAILWAY_PUBLIC_DOMAIN}"
fi
if [ -z "${WP_SITEURL:-}" ] && [ -n "${WP_HOME:-}" ]; then
  export WP_SITEURL="${WP_HOME}"
fi

if [ -z "${WORDPRESS_DB_HOST:-}" ] || [ -z "${WORDPRESS_DB_USER:-}" ] || [ -z "${WORDPRESS_DB_PASSWORD:-}" ] || [ -z "${WORDPRESS_DB_NAME:-}" ]; then
  echo "ERRO: faltam variaveis de banco."
  echo "No servico WordPress, adicione (Variables -> Raw Editor), trocando MySQL pelo nome do seu servico:"
  echo "  WORDPRESS_DB_HOST=\${{MySQL.MYSQLHOST}}:\${{MySQL.MYSQLPORT}}"
  echo "  WORDPRESS_DB_USER=\${{MySQL.MYSQLUSER}}"
  echo "  WORDPRESS_DB_PASSWORD=\${{MySQL.MYSQLPASSWORD}}"
  echo "  WORDPRESS_DB_NAME=\${{MySQL.MYSQLDATABASE}}"
  echo "Ou use referencias MYSQLHOST/MYSQLUSER/MYSQLPASSWORD/MYSQLDATABASE."
  exit 1
fi

echo "DB host=${WORDPRESS_DB_HOST} user=${WORDPRESS_DB_USER} name=${WORDPRESS_DB_NAME}"

# Trecho injetado no wp-config gerado pela imagem oficial do WordPress
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

# Garante um unico MPM antes do Apache subir (fix Railway)
a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

exec docker-entrypoint.sh "$@"
