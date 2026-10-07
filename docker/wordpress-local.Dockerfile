# Localhost: WordPress PHP 8.3 + extensão Redis (cache de objeto).
FROM wordpress:php8.3-apache

RUN set -eux; \
  apt-get update; \
  apt-get install -y --no-install-recommends $PHPIZE_DEPS; \
  pecl install redis; \
  docker-php-ext-enable redis; \
  apt-get purge -y --auto-remove $PHPIZE_DEPS; \
  rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite expires headers deflate 2>/dev/null || true
