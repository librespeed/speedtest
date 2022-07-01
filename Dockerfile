FROM php:7.4-apache

# Install extensions
RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libapache2-mod-security2 \
    && docker-php-ext-install -j$(nproc) iconv \
    && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install -j$(nproc) gd

# Prepare files and folders

RUN mkdir -p /speedtest/

# Copy sources

COPY backend/ /speedtest/backend

# TLS1.3
COPY docker/etc/ports.conf /etc/apache2/ports.conf
COPY docker/etc/default-ssl.conf /etc/apache2/sites-enabled/default-ssl.conf
COPY docker/etc/ssl.conf /etc/apache2/mods-available/ssl.conf
## Certificate and key
COPY cert/cert.pem /etc/ssl/certs/cert.pem
COPY cert/key.pem /etc/ssl/private/key.pem

COPY results/*.php /speedtest/results/
COPY results/*.ttf /speedtest/results/

COPY *.js /speedtest/
COPY favicon.ico /speedtest/

COPY docker/servers.json /servers.json

COPY docker/*.php /speedtest/
COPY docker/entrypoint.sh /

# Prepare environment variabiles defaults

ENV TITLE=LibreSpeed
ENV MODE=standalone
ENV PASSWORD=password
ENV TELEMETRY=false
ENV ENABLE_ID_OBFUSCATION=false
ENV REDACT_IP_ADDRESSES=false
ENV WEBPORT=80

# Final touches

EXPOSE 443
CMD ["bash", "/entrypoint.sh"]
