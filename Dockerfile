FROM php:8.5-fpm-alpine
LABEL maintainer="garth.gillespie@gmail.com"
LABEL description="RWLL player evaluations - Apache and php-fpm 8.5 in one Alpine container"

# Same layout as Football-NG: Apache from Alpine proxies .php to php-fpm over a unix socket.
# local build: scripts/local-dev.sh build (container build -t rwll-evals:local .)

RUN apk upgrade --no-cache && \
    apk add --no-cache apache2 apache2-proxy curl

# pdo_mysql for the database; gd + exif re-encode a photo that didn't come through the phone's resize (src/Photos.php)
RUN set -xe && \
    apk add --no-cache libpng libwebp libjpeg-turbo && \
    apk add --no-cache --virtual .build-deps libpng-dev libwebp-dev libjpeg-turbo-dev && \
    docker-php-ext-configure gd --with-jpeg --with-webp && \
    docker-php-ext-install -j$(nproc) pdo_mysql gd exif && \
    apk del .build-deps && \
    rm -rf /tmp/*

# Datadog APM - the ddtrace extension sends traces to the agent on the host (DD_AGENT_HOST in deploy/compose.yaml).
# Local dev has no agent, so local-dev.sh turns tracing off. libgcc: the musl build needs it.
ARG DD_TRACE_VERSION=1.27.1
RUN apk add --no-cache libgcc && \
    curl -fsSL -o /tmp/datadog-setup.php \
        https://github.com/DataDog/dd-trace-php/releases/download/${DD_TRACE_VERSION}/datadog-setup.php && \
    php /tmp/datadog-setup.php --php-bin=all && \
    # tracing only - the installer always adds the (switched off) security-monitoring extension
    sed -i 's/^extension = ddappsec/; extension = ddappsec/' /usr/local/etc/php/conf.d/98-ddtrace.ini && \
    rm -rf /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV APP_ROOT=/var/www/rwll \
    HTTP_PORT=80 \
    LOGLEVEL=warn \
    TZ=America/Los_Angeles

WORKDIR ${APP_ROOT}

# the AWS SDK (S3 only - composer.json extra) for photo uploads
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader && rm -rf /root/.composer
COPY . .
RUN composer dump-autoload --no-dev --optimize

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    cp docker/php.ini "$PHP_INI_DIR/conf.d/zz-rwll.ini" && \
    # php-fpm: unix socket, keep the container env (DB_*, PHOTO_*, ADMIN_PIN), no access log (Apache has it)
    { echo '[www]'; \
      echo 'listen = /run/php/php-fpm.sock'; \
      echo 'listen.owner = www-data'; \
      echo 'listen.group = www-data'; \
      echo 'listen.mode = 0660'; \
      echo 'clear_env = no'; \
      echo 'access.log = /dev/null'; \
      echo 'pm.max_children = 10'; \
    } > /usr/local/etc/php-fpm.d/zz-rwll.conf && \
    # Apache: event mpm, rewrite, proxy_fcgi, headers; drop what we don't use
    sed -i -e 's/^#\(LoadModule rewrite_module\)/\1/' \
           -e 's/^#\(LoadModule deflate_module\)/\1/' \
           -e 's/^#\(LoadModule expires_module\)/\1/' \
           -e 's/^#\(LoadModule remoteip_module\)/\1/' \
           -e 's/^#\(LoadModule mpm_event_module\)/\1/' \
           -e 's/^\(LoadModule mpm_prefork_module\)/#\1/' \
           -e 's/^\(LoadModule autoindex_module\)/#\1/' \
           -e 's/^User apache/User www-data/' \
           -e 's/^Group apache/Group www-data/' /etc/apache2/httpd.conf && \
    sed -i -e 's/^LoadModule/#LoadModule/' \
           -e 's/^#\(LoadModule proxy_module\)/\1/' \
           -e 's/^#\(LoadModule proxy_fcgi_module\)/\1/' /etc/apache2/conf.d/proxy.conf && \
    echo "Include ${APP_ROOT}/docker/apache-rwll.conf" > /etc/apache2/conf.d/zzz-rwll.conf && \
    mkdir -p /run/php /run/apache2 storage/photos && \
    chown -R www-data:www-data /run/php /run/apache2 /var/log/apache2 storage && \
    chmod +x docker/entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/var/www/rwll/docker/entrypoint.sh"]
