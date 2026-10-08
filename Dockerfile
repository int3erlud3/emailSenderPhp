# syntax=docker/dockerfile:1
# Demo image for the contact form (Apache + PHP). Dependencies are installed in a
# separate stage so composer itself never ends up in the runtime image.
FROM composer:2.10.3 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --no-autoloader
COPY src ./src
RUN composer dump-autoload --no-dev --classmap-authoritative

FROM php:8.4.26-apache-trixie
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'expose_php = Off\npost_max_size = 64K\nupload_max_filesize = 0\nfile_uploads = Off\nsession.save_path = /tmp\n' \
      > "$PHP_INI_DIR/conf.d/zz-hardening.ini" \
 && sed -ri 's!^Listen 80$!Listen 8080!' /etc/apache2/ports.conf \
 && sed -ri 's!<VirtualHost \*:80>!<VirtualHost *:8080>!; s!/var/www/html!/var/www/app/public!g' /etc/apache2/sites-available/000-default.conf \
 && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\n<Directory /var/www/app/public>\n  Options -Indexes -FollowSymLinks\n  AllowOverride None\n  Require all granted\n</Directory>\n' \
      > /etc/apache2/conf-enabled/zz-hardening.conf
WORKDIR /var/www/app
COPY --from=vendor /app/vendor ./vendor
COPY src ./src
COPY public ./public
COPY bin ./bin
USER www-data
EXPOSE 8080
HEALTHCHECK --interval=10s --timeout=3s --retries=5 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1:8080/assets/style.css") === false ? 1 : 0);'
