FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends cron curl unzip \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite
COPY docker/vhost.conf /etc/apache2/sites-available/000-default.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader

COPY . .

# Check endpoints every minute from inside the container
COPY docker/crontab /etc/cron.d/monitor
RUN chmod 0644 /etc/cron.d/monitor

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

HEALTHCHECK --interval=60s --timeout=10s --start-period=15s \
    CMD curl -fs http://localhost/favicon.ico -o /dev/null || exit 1

ENTRYPOINT ["entrypoint.sh"]
