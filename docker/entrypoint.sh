#!/bin/sh
set -e

# The data volume may be created root-owned by docker; the app writes as www-data
mkdir -p /var/www/html/data
chown -R www-data:www-data /var/www/html/data

cron
exec apache2-foreground
