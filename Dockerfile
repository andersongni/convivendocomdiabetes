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

RUN chmod +x /usr/local/bin/prod-entrypoint.sh \
  && chown -R www-data:www-data /var/www/html

ENV PORT=80

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/prod-entrypoint.sh"]
CMD ["apache2-foreground"]
