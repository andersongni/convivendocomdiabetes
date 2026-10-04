#!/bin/bash
set -euo pipefail

# Defaults / Railway private DNS
export WORDPRESS_DB_HOST="${WORDPRESS_DB_HOST:-db:3306}"
export WORDPRESS_DB_USER="${WORDPRESS_DB_USER:-wpapp}"
export WORDPRESS_DB_PASSWORD="${WORDPRESS_DB_PASSWORD:-}"
export WORDPRESS_DB_NAME="${WORDPRESS_DB_NAME:-wordpress}"

# Mapeia plugin MySQL gerenciado (Railway) se as vars WP nao vierem preenchidas
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

# Credenciais do primeiro admin (troca obrigatoria no primeiro acesso)
export WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
export WP_ADMIN_PASSWORD="${WP_ADMIN_PASSWORD:-CcdTrocar123!}"
export WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@convivendocomdiabetes.com}"
export WP_TITLE="${WP_TITLE:-Convivendo com Diabetes}"

if [ -z "${WORDPRESS_DB_PASSWORD}" ]; then
  echo "[wp] ERRO: WORDPRESS_DB_PASSWORD vazia"
  exit 1
fi

echo "[wp] DB host=${WORDPRESS_DB_HOST} user=${WORDPRESS_DB_USER} name=${WORDPRESS_DB_NAME}"
echo "[wp] HOME=${WP_HOME:-"(pendente dominio)"}"

# Remove config local se veio na imagem; o boot gera a partir das env vars
rm -f /var/www/html/wp-config.php

a2dismod mpm_event 2>/dev/null || true
a2dismod mpm_worker 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# docker-entrypoint prepara o filesystem do WordPress; em seguida sobe o app
exec docker-entrypoint.sh bash /usr/local/bin/wp-boot.sh
