#!/bin/bash
# Roda só na primeira inicialização do volume MySQL (imagem oficial).
set -euo pipefail

SOURCE_DB="convivend62e9f3c_ccd"
TARGET_DB="${MYSQL_DATABASE:-wordpress}"
# Nome sem .sql.gz para a imagem MySQL nao importar sozinha (o .sh trata o dump)
DUMP_GZ="/docker-entrypoint-initdb.d/site-dump.gz"

echo "[init] Importando dump -> banco '${TARGET_DB}'..."

if [ ! -f "$DUMP_GZ" ]; then
  echo "[init] ERRO: ${DUMP_GZ} nao encontrado"
  exit 1
fi

gunzip -c "$DUMP_GZ" \
  | sed "s/\`${SOURCE_DB}\`/\`${TARGET_DB}\`/g" \
  | mysql -uroot -p"${MYSQL_ROOT_PASSWORD}"

# Garante privilegios do usuario da aplicacao (nao-root) no banco importado
if [ -n "${MYSQL_USER:-}" ] && [ -n "${MYSQL_PASSWORD:-}" ]; then
  echo "[init] Garantindo grants para '${MYSQL_USER}' em '${TARGET_DB}'..."
  mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'%' IDENTIFIED BY '${MYSQL_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${TARGET_DB}\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
fi

echo "[init] Dump importado com sucesso."
