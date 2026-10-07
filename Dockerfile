FROM wordpress:cli-php8.3 AS wpcli

FROM wordpress:php8.3-apache

# Redis extension (cache de objeto quando WP_REDIS_HOST estiver definido)
RUN set -eux; \
  apt-get update; \
  apt-get install -y --no-install-recommends $PHPIZE_DEPS; \
  pecl install redis; \
  docker-php-ext-enable redis; \
  apt-get purge -y --auto-remove $PHPIZE_DEPS; \
  rm -rf /var/lib/apt/lists/*

# Railway/Debian: MPM único + ServerName (AH00558) + performance
RUN a2dismod mpm_event 2>/dev/null || true \
  && a2dismod mpm_worker 2>/dev/null || true \
  && a2enmod mpm_prefork rewrite expires headers deflate 2>/dev/null || true \
  && printf '%s\n' 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
  && a2enconf servername

COPY --from=wpcli /usr/local/bin/wp /usr/local/bin/wp
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache-prod.ini
COPY docker/apache-performance.conf /etc/apache2/conf-available/performance.conf
COPY docker/ccdready.php /tmp/ccdready.php

# Core oficial (temas twenty* + WP atual) + conteudo do site
COPY wordpress/wp-content/ /tmp/site-wp-content/
COPY wordpress/.htaccess /tmp/site-htaccess
COPY db/schema.sql /opt/schema.sql
COPY scripts/prod-entrypoint.sh /usr/local/bin/prod-entrypoint.sh
COPY scripts/wp-boot.sh /usr/local/bin/wp-boot.sh
COPY scripts/bind-apache-ports.sh /usr/local/bin/bind-apache-ports.sh

RUN set -eux; \
  cp -a /usr/src/wordpress/. /var/www/html/; \
  rm -rf /var/www/html/wp-content; \
  mv /tmp/site-wp-content /var/www/html/wp-content; \
  cp -a /tmp/site-htaccess /var/www/html/.htaccess; \
  rm -f /tmp/site-htaccess; \
  mkdir -p /var/www/html/wp-content/uploads; \
  # Remove artefatos locais / cache que nao devem ir para producao
  rm -rf \
    /var/www/html/wp-content/upgrade-temp-backup \
    /var/www/html/wp-content/w3tc-config \
    /var/www/html/wp-content/cache; \
  rm -f /var/www/html/wp-content/wp-cache-config.php \
    /var/www/html/wp-content/plugins/*.zip; \
  # Nao copiar temas twenty* inativos (Site Health / superficie de ataque).
  # Ativo: empowerwp (filho) + mesmerize (pai), ja em site-wp-content.
  rm -rf /var/www/html/wp-content/themes/twenty*; \
  # Redis drop-in so e instalado em runtime (wp-boot) se WP_REDIS_HOST existir.
  # Nao copiar na imagem: sem Redis no Railway o drop-in derruba o boot (502).
  rm -f /var/www/html/wp-content/object-cache.php; \
  a2enconf performance; \
  sed -i 's/\r$//' /usr/local/bin/prod-entrypoint.sh /usr/local/bin/wp-boot.sh /usr/local/bin/bind-apache-ports.sh; \
  chmod +x /usr/local/bin/prod-entrypoint.sh /usr/local/bin/wp-boot.sh /usr/local/bin/bind-apache-ports.sh; \
  rm -f /var/www/html/wp-config.php; \
  # Liveness /ccdhealth (estatico) + readiness /ccdready (DB; sem ponto no path).
  printf 'ok\n' > /var/www/html/ccdhealth; \
  test -f /var/www/html/wp-content/ccd-health-ok.txt || printf 'ok\n' > /var/www/html/wp-content/ccd-health-ok.txt; \
  sed 's/\r$//' /tmp/ccdready.php > /var/www/html/ccdready.php; \
  rm -f /tmp/ccdready.php; \
  chown -R www-data:www-data /var/www/html

ENV PORT=80

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/prod-entrypoint.sh"]
CMD ["apache2-foreground"]
