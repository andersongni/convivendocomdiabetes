FROM wordpress:cli-php8.2 AS wpcli

FROM wordpress:php8.2-apache

# Railway/Debian: evita "More than one MPM loaded"
RUN a2dismod mpm_event 2>/dev/null || true \
  && a2dismod mpm_worker 2>/dev/null || true \
  && a2enmod mpm_prefork 2>/dev/null || true

COPY --from=wpcli /usr/local/bin/wp /usr/local/bin/wp

COPY wordpress/ /var/www/html/
COPY db/schema.sql /opt/schema.sql
COPY scripts/prod-entrypoint.sh /usr/local/bin/prod-entrypoint.sh
COPY scripts/wp-boot.sh /usr/local/bin/wp-boot.sh

# O COPY do site UOL sobrescreve os temas default; restaura um tema core para a home nao ficar em branco.
RUN set -eux; \
  for theme in twentytwentyfive twentytwentyfour twentytwentythree twentytwentytwo twentytwentyone twentytwenty; do \
    if [ -d "/usr/src/wordpress/wp-content/themes/$theme" ]; then \
      cp -a "/usr/src/wordpress/wp-content/themes/$theme" /var/www/html/wp-content/themes/; \
    fi; \
  done; \
  sed -i 's/\r$//' /usr/local/bin/prod-entrypoint.sh /usr/local/bin/wp-boot.sh; \
  chmod +x /usr/local/bin/prod-entrypoint.sh /usr/local/bin/wp-boot.sh; \
  rm -f /var/www/html/wp-config.php; \
  chown -R www-data:www-data /var/www/html

ENV PORT=80

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/prod-entrypoint.sh"]
CMD ["apache2-foreground"]
