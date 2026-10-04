FROM wordpress:cli-php7.4 AS wpcli

FROM wordpress:php7.4-apache

# MariaDB local + cliente + WP-CLI (um unico servico no Railway)
RUN a2dismod mpm_event 2>/dev/null || true \
  && a2dismod mpm_worker 2>/dev/null || true \
  && a2enmod mpm_prefork 2>/dev/null || true \
  && apt-get update \
  && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
       mariadb-server \
       mariadb-client \
  && rm -rf /var/lib/apt/lists/* \
  && mkdir -p /var/run/mysqld \
  && chown mysql:mysql /var/run/mysqld

COPY --from=wpcli /usr/local/bin/wp /usr/local/bin/wp

COPY wordpress/ /var/www/html/
COPY db/init/site-dump.gz /opt/site-dump.gz
COPY scripts/all-in-one-entrypoint.sh /usr/local/bin/all-in-one-entrypoint.sh

RUN chmod +x /usr/local/bin/all-in-one-entrypoint.sh \
  && chown -R www-data:www-data /var/www/html

ENV PORT=80 \
    MYSQL_DATABASE=wordpress \
    MYSQL_USER=wpapp

EXPOSE 80
VOLUME ["/var/lib/mysql"]

ENTRYPOINT ["/usr/local/bin/all-in-one-entrypoint.sh"]
