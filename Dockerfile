FROM wordpress:php7.4-apache

# Railway/Debian: evita "More than one MPM loaded"
RUN a2dismod mpm_event 2>/dev/null || true \
  && a2dismod mpm_worker 2>/dev/null || true \
  && a2enmod mpm_prefork 2>/dev/null || true

# Copia o site versionado (wp-config.php fica de fora — gerado pelo entrypoint via env)
COPY wordpress/ /var/www/html/

COPY scripts/prod-entrypoint.sh /usr/local/bin/prod-entrypoint.sh
RUN chmod +x /usr/local/bin/prod-entrypoint.sh \
  && chown -R www-data:www-data /var/www/html

ENTRYPOINT ["/usr/local/bin/prod-entrypoint.sh"]
CMD ["apache2-foreground"]
