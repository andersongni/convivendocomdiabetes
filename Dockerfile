FROM wordpress:cli-php7.4 AS wpcli

FROM wordpress:php7.4-apache

# Railway/Debian: evita "More than one MPM loaded"
RUN a2dismod mpm_event 2>/dev/null || true \
  && a2dismod mpm_worker 2>/dev/null || true \
  && a2enmod mpm_prefork 2>/dev/null || true \
  && apt-get update \
  && apt-get install -y --no-install-recommends default-mysql-client \
  && rm -rf /var/lib/apt/lists/*

COPY --from=wpcli /usr/local/bin/wp /usr/local/bin/wp

# Copia o site versionado (wp-config.php fica de fora — gerado via env)
COPY wordpress/ /var/www/html/

COPY scripts/prod-entrypoint.sh /usr/local/bin/prod-entrypoint.sh
RUN chmod +x /usr/local/bin/prod-entrypoint.sh \
  && chown -R www-data:www-data /var/www/html

ENTRYPOINT ["/usr/local/bin/prod-entrypoint.sh"]
CMD ["apache2-foreground"]
