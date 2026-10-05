#!/bin/bash
# Railway healthcheck reaches $PORT; the public service domain is pinned to :80.
# Listen on both so deploys stay healthy and the edge stops returning 502.
set -euo pipefail

PORT="${PORT:-80}"
PORTS_CONF="${PORTS_CONF:-/etc/apache2/ports.conf}"
SITE_CONF="${SITE_CONF:-/etc/apache2/sites-available/000-default.conf}"

if [ -f "$PORTS_CONF" ]; then
  grep -vE '^Listen ' "$PORTS_CONF" > "${PORTS_CONF}.tmp" || true
  {
    echo "Listen ${PORT}"
    if [ "${PORT}" != "80" ]; then
      echo "Listen 80"
    fi
  } >> "${PORTS_CONF}.tmp"
  mv "${PORTS_CONF}.tmp" "$PORTS_CONF"
fi

if [ -f "$SITE_CONF" ]; then
  if [ "${PORT}" = "80" ]; then
    sed -ri 's/<VirtualHost \*:[0-9]+( \*:[0-9]+)*>/<VirtualHost *:80>/' "$SITE_CONF"
  else
    sed -ri "s/<VirtualHost \\*:[0-9]+( \\*:[0-9]+)*>/<VirtualHost *:80 *:${PORT}>/" "$SITE_CONF"
  fi
fi

if [ "${PORT}" != "80" ]; then
  echo "[wp] Apache listening on PORT=${PORT} and 80"
else
  echo "[wp] Apache listening on PORT=80"
fi
