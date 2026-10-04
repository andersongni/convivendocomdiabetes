#!/bin/bash
set -euo pipefail

# URLs públicas (Railway injeta RAILWAY_PUBLIC_DOMAIN)
if [ -z "${WP_HOME:-}" ] && [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
  export WP_HOME="https://${RAILWAY_PUBLIC_DOMAIN}"
fi
if [ -z "${WP_SITEURL:-}" ] && [ -n "${WP_HOME:-}" ]; then
  export WP_SITEURL="${WP_HOME}"
fi

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
