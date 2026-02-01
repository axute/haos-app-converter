#!/bin/sh
set -e

# Sicherstellen, dass das data-Verzeichnis existiert
mkdir -p /var/www/html/data

# Berechtigungen für das data-Verzeichnis setzen, damit der Webserver (www-data) schreiben kann
chown -R www-data:www-data /var/www/html/data
chmod -R 775 /var/www/html/data

# Den ursprünglichen Befehl (Apache) ausführen
exec apache2-foreground
