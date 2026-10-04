#!/usr/bin/env bash
# Importa DADOS do dump (complementar ao schema automatico do deploy).
#
# Local:
#   ./scripts/import-dump.sh
#
# Remoto (Railway TCP Proxy):
#   DB_HOST=xxx DB_PORT=12345 DB_USER=root DB_PASSWORD=xxx DB_NAME=railway \
#   SITE_URL=https://seu-dominio.up.railway.app \
#   ./scripts/import-dump.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-wordpress}"
DB_PASSWORD="${DB_PASSWORD:-wordpress}"
DB_NAME="${DB_NAME:-convivend62e9f3c_ccd}"
SOURCE_DB="convivend62e9f3c_ccd"
SITE_URL="${SITE_URL:-}"

if [ -f dump-database.sql ]; then
  DUMP=dump-database.sql
elif [ -f db/init/site-dump.gz ]; then
  DUMP=db/init/site-dump.gz
else
  echo "Dump nao encontrado (dump-database.sql ou db/init/site-dump.gz)"
  exit 1
fi

MYSQL_HOST="$DB_HOST"
if [ "$DB_HOST" = "127.0.0.1" ] || [ "$DB_HOST" = "localhost" ]; then
  MYSQL_HOST="host.docker.internal"
fi

echo "Importando $DUMP -> ${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}"

if [[ "$DUMP" == *.gz ]]; then
  docker run --rm -v "$ROOT:/work" -w /work mysql:8.0 \
    bash -lc "gunzip -c '$DUMP' | sed 's/\`${SOURCE_DB}\`/\`${DB_NAME}\`/g' | mysql -h'$MYSQL_HOST' -P$DB_PORT -u'$DB_USER' -p'$DB_PASSWORD' --ssl-mode=PREFERRED '$DB_NAME'"
else
  docker run --rm -v "$ROOT:/work" -w /work mysql:8.0 \
    bash -lc "sed 's/\`${SOURCE_DB}\`/\`${DB_NAME}\`/g' '$DUMP' | mysql -h'$MYSQL_HOST' -P$DB_PORT -u'$DB_USER' -p'$DB_PASSWORD' --ssl-mode=PREFERRED '$DB_NAME'"
fi

if [ -n "$SITE_URL" ]; then
  echo "Atualizando siteurl/home -> $SITE_URL"
  docker run --rm mysql:8.0 \
    mysql -h"$MYSQL_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASSWORD" --ssl-mode=PREFERRED "$DB_NAME" \
    -e "UPDATE wp_options SET option_value='${SITE_URL}' WHERE option_name IN ('siteurl','home');"
fi

echo "Dump importado com sucesso."
