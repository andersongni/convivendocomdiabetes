FROM wordpress:cli-php8.2 AS wpcli

FROM wordpress:php8.2-apache

# Railway/Debian: MPM único + ServerName (AH00558) + performance
RUN a2dismod mpm_event 2>/dev/null || true \
  && a2dismod mpm_worker 2>/dev/null || true \
  && a2enmod mpm_prefork rewrite expires headers deflate 2>/dev/null || true \
  && printf '%s\n' 'ServerName localhost' > /etc/apache2/conf-available/servername.conf \
  && a2enconf servername

COPY --from=wpcli /usr/local/bin/wp /usr/local/bin/wp
COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache-prod.ini
COPY docker/apache-performance.conf /etc/apache2/conf-available/performance.conf

# Core oficial (temas twenty* + WP atual) + conteudo do site
COPY wordpress/wp-content/ /tmp/site-wp-content/
COPY db/schema.sql /opt/schema.sql
COPY scripts/prod-entrypoint.sh /usr/local/bin/prod-entrypoint.sh
COPY scripts/wp-boot.sh /usr/local/bin/wp-boot.sh
COPY scripts/bind-apache-ports.sh /usr/local/bin/bind-apache-ports.sh

RUN set -eux; \
  cp -a /usr/src/wordpress/. /var/www/html/; \
  rm -rf /var/www/html/wp-content; \
  mv /tmp/site-wp-content /var/www/html/wp-content; \
  mkdir -p /var/www/html/wp-content/uploads; \
  # Remove artefatos locais / cache que nao devem ir para producao
  rm -rf \
    /var/www/html/wp-content/upgrade-temp-backup \
    /var/www/html/wp-content/w3tc-config \
    /var/www/html/wp-content/cache; \
  rm -f /var/www/html/wp-content/wp-cache-config.php \
    /var/www/html/wp-content/plugins/*.zip; \
  for theme in twentytwentyfive twentytwentyfour twentytwentythree twentytwentytwo twentytwentyone twentytwenty; do \
    if [ -d "/usr/src/wordpress/wp-content/themes/$theme" ] && [ ! -d "/var/www/html/wp-content/themes/$theme" ]; then \
      cp -a "/usr/src/wordpress/wp-content/themes/$theme" /var/www/html/wp-content/themes/; \
    fi; \
  done; \
  a2enconf performance; \
  sed -i 's/\r$//' /usr/local/bin/prod-entrypoint.sh /usr/local/bin/wp-boot.sh /usr/local/bin/bind-apache-ports.sh; \
  chmod +x /usr/local/bin/prod-entrypoint.sh /usr/local/bin/wp-boot.sh /usr/local/bin/bind-apache-ports.sh; \
  rm -f /var/www/html/wp-config.php; \
  chown -R www-data:www-data /var/www/html

ENV PORT=80

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/prod-entrypoint.sh"]
CMD ["apache2-foreground"]
