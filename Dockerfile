FROM alpine:3.19 AS crane

RUN apk add --no-cache curl tar

# crane installieren (immer latest)
RUN ARCH=$(uname -m) && \
    case "$ARCH" in \
      x86_64)   CRANE_ARCH=x86_64 ;; \
      aarch64)  CRANE_ARCH=arm64 ;; \
      armv7l)   CRANE_ARCH=armv6 ;; \
      *)        echo "Unsupported arch: $ARCH" && exit 1 ;; \
    esac && \
    VERSION=$(curl -s https://api.github.com/repos/google/go-containerregistry/releases/latest \
              | grep tag_name | cut -d '"' -f 4) && \
    URL="https://github.com/google/go-containerregistry/releases/download/${VERSION}/go-containerregistry_Linux_${CRANE_ARCH}.tar.gz" && \
    echo "Downloading $URL" && \
    curl -sSL "$URL" -o /tmp/crane.tar.gz && \
    tar -xzf /tmp/crane.tar.gz -C /usr/local/bin crane && \
    chmod +x /usr/local/bin/crane && \
    rm /tmp/crane.tar.gz


FROM php:8.3-apache

# Apache mod_rewrite aktivieren für Slim Framework
RUN a2enmod rewrite

# Benötigte System-Abhängigkeiten installieren
RUN apt-get update && apt-get install -y \
    unzip \
    libzip-dev \
    git \
    jq \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=crane /usr/local/bin/crane /usr/local/bin/crane
RUN chmod +x /usr/local/bin/crane
# Arbeitsverzeichnis setzen
WORKDIR /var/www/html

# Composer kopieren (Multi-stage build approach)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Projektdateien kopieren
COPY . .

# Abhängigkeiten installieren
RUN composer install --no-interaction --optimize-autoloader && php install.php

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
