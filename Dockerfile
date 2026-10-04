FROM wordpress:php7.4-apache

# Copia o site versionado (wp-config.php fica de fora — gerado pelo entrypoint via env)
COPY wordpress/ /var/www/html/

COPY scripts/prod-entrypoint.sh /usr/local/bin/prod-entrypoint.sh
RUN chmod +x /usr/local/bin/prod-entrypoint.sh \
  && chown -R www-data:www-data /var/www/html

ENTRYPOINT ["/usr/local/bin/prod-entrypoint.sh"]
CMD ["apache2-foreground"]
