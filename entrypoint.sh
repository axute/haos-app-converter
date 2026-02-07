#!/bin/sh
set -e

# Pfad aus Umgebungsvariable oder Standardpfad
DATA_DIR="${CONVERTER_DATA_DIR:-/var/www/html/data}"

# Sicherstellen, dass das data-Verzeichnis existiert
mkdir -p "$DATA_DIR"

# Berechtigungen für das data-Verzeichnis setzen, damit der Webserver (www-data) schreiben kann
chown -R www-data:www-data "$DATA_DIR"
chmod -R 775 "$DATA_DIR"

# Den ursprünglichen Befehl (Apache) ausführen
exec apache2-foreground
