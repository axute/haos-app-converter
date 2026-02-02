FROM php:8.3-apache

# Apache mod_rewrite aktivieren für Slim Framework
RUN a2enmod rewrite

# Benötigte System-Abhängigkeiten installieren
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    && rm -rf /var/www/html/var/lib/apt/lists/*

# Arbeitsverzeichnis setzen
WORKDIR /var/www/html

# Composer kopieren (Multi-stage build approach)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Projektdateien kopieren
COPY . .

# Abhängigkeiten installieren
RUN composer install --no-interaction --optimize-autoloader

# Berechtigungen für das Datenverzeichnis setzen
RUN mkdir -p data && chown -R www-data:www-data data

# Apache DocumentRoot auf /public setzen und AllowOverride aktivieren
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# AllowOverride All setzen, damit .htaccess funktioniert
RUN echo "<Directory /var/www/html/public>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>" >> /etc/apache2/apache2.conf

# Port 80 freigeben
EXPOSE 80

# Entrypoint Script hinzufügen und ausführbar machen
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
