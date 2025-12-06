FROM php:8-apache

# use docker-php-extension-installer for automatically get the right packages installed
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Install extensions and cleanup in a single layer to reduce image size
RUN install-php-extensions iconv gd pdo pdo_mysql pdo_pgsql pgsql \
    && rm -f /usr/src/php.tar.xz /usr/src/php.tar.xz.asc \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Prepare files and folders
RUN mkdir -p /speedtest/

# Copy sources
COPY backend/ /speedtest/backend

COPY results/*.php /speedtest/results/
COPY results/*.ttf /speedtest/results/

COPY *.js /speedtest/
COPY favicon.ico /speedtest/

COPY docker/servers.json /servers.json

COPY docker/*.php /speedtest/
COPY docker/entrypoint.sh /

# Prepare default environment variables
ENV TITLE=LibreSpeed
ENV MODE=standalone
ENV PASSWORD=password
ENV TELEMETRY=false
ENV ENABLE_ID_OBFUSCATION=false
ENV REDACT_IP_ADDRESSES=false
ENV WEBPORT=8080

# https://httpd.apache.org/docs/2.4/stopping.html#gracefulstop
STOPSIGNAL SIGWINCH

# Add labels for better metadata
LABEL org.opencontainers.image.title="LibreSpeed"
LABEL org.opencontainers.image.description="A Free and Open Source speed test that you can host on your server(s)"
LABEL org.opencontainers.image.vendor="LibreSpeed"
LABEL org.opencontainers.image.url="https://github.com/librespeed/speedtest"
LABEL org.opencontainers.image.source="https://github.com/librespeed/speedtest"
LABEL org.opencontainers.image.documentation="https://github.com/librespeed/speedtest/blob/master/doc_docker.md"
LABEL org.opencontainers.image.licenses="LGPL-3.0-or-later"

# Add health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost:${WEBPORT}/ || exit 1

# Final touches
EXPOSE ${WEBPORT}
CMD ["bash", "/entrypoint.sh"]
