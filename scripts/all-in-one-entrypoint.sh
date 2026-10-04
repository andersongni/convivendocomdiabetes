#!/bin/bash
set -euo pipefail

MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-changeme-root}"
MYSQL_DATABASE="${MYSQL_DATABASE:-wordpress}"
MYSQL_USER="${MYSQL_USER:-wpapp}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-changeme-wpapp}"
SOURCE_DB="convivend62e9f3c_ccd"
DUMP_GZ="${DUMP_GZ:-/opt/site-dump.gz}"

export WORDPRESS_DB_HOST="127.0.0.1:3306"
export WORDPRESS_DB_USER="${MYSQL_USER}"
export WORDPRESS_DB_PASSWORD="${MYSQL_PASSWORD}"
export WORDPRESS_DB_NAME="${MYSQL_DATABASE}"

if [ -z "${WP_HOME:-}" ] && [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
  export WP_HOME="https://${RAILWAY_PUBLIC_DOMAIN}"
fi
if [ -z "${WP_SITEURL:-}" ] && [ -n "${WP_HOME:-}" ]; then
  export WP_SITEURL="${WP_HOME}"
fi

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

mkdir -p /var/run/mysqld
chown -R mysql:mysql /var/run/mysqld /var/lib/mysql

mysql_root() {
  # tenta socket/root sem senha; depois com senha
  if mysql -uroot --protocol=socket -e "SELECT 1" >/dev/null 2>&1; then
    mysql -uroot --protocol=socket "$@"
  elif mysql -h127.0.0.1 -uroot -e "SELECT 1" >/dev/null 2>&1; then
    mysql -h127.0.0.1 -uroot "$@"
  else
    mysql -h127.0.0.1 -uroot -p"${MYSQL_ROOT_PASSWORD}" "$@"
  fi
}

echo "[boot] Iniciando MariaDB..."
if [ ! -d /var/lib/mysql/mysql ]; then
  echo "[boot] Inicializando datadir"
  if command -v mariadb-install-db >/dev/null 2>&1; then
    mariadb-install-db --user=mysql --datadir=/var/lib/mysql >/tmp/mysql-install.log 2>&1
  else
    mysql_install_db --user=mysql --datadir=/var/lib/mysql >/tmp/mysql-install.log 2>&1
  fi
fi

mysqld --user=mysql --datadir=/var/lib/mysql --bind-address=127.0.0.1 --skip-networking=0 &

echo "[boot] Aguardando MySQL..."
for i in $(seq 1 90); do
  if mysqladmin ping -h127.0.0.1 --silent 2>/dev/null \
     || mysqladmin ping --protocol=socket --silent 2>/dev/null; then
    echo "[boot] MySQL UP"
    break
  fi
  sleep 2
done

echo "[boot] Bootstrap usuario app..."
mysql_root <<SQL
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'%' IDENTIFIED BY '${MYSQL_PASSWORD}';
CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'localhost' IDENTIFIED BY '${MYSQL_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_USER}'@'%';
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

TABLES="$(mysql -h127.0.0.1 -uroot -p"${MYSQL_ROOT_PASSWORD}" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${MYSQL_DATABASE}' AND table_name='wp_options';" 2>/dev/null || echo 0)"
if [ "${TABLES}" = "0" ]; then
  echo "[boot] Importando dump (primeira vez)..."
  gunzip -c "${DUMP_GZ}" \
    | sed "s/\`${SOURCE_DB}\`/\`${MYSQL_DATABASE}\`/g" \
    | mysql -h127.0.0.1 -uroot -p"${MYSQL_ROOT_PASSWORD}"
  echo "[boot] Dump importado."
else
  echo "[boot] Banco ja populado — pulando import."
fi

echo "[boot] Preparando WordPress..."
exec docker-entrypoint.sh bash -c '
  set -e
  for i in $(seq 1 60); do
    if wp db check --allow-root --path=/var/www/html >/dev/null 2>&1; then
      break
    fi
    sleep 2
  done

  if [ -n "${WP_HOME:-}" ]; then
    echo "[boot] URLs -> ${WP_HOME}"
    wp option update home "${WP_HOME}" --allow-root --path=/var/www/html || true
    wp option update siteurl "${WP_SITEURL:-$WP_HOME}" --allow-root --path=/var/www/html || true
    wp search-replace "https://www.convivendocomdiabetes.com" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
    wp search-replace "http://www.convivendocomdiabetes.com" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
    wp search-replace "https://convivendocomdiabetes.com" "${WP_HOME}" --all-tables --skip-columns=guid --allow-root --path=/var/www/html || true
  fi

  echo "[boot] Apache pronto"
  exec apache2-foreground
'
