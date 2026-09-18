#syntax=docker/dockerfile:1

# Configuration éprouvée du starter officiel Symfony pour FrankenPHP
# (https://github.com/dunglas/symfony-docker), adaptée pour ce projet :
# ajout de pdo_mysql (base principale) et mongodb (statistiques).

FROM dunglas/frankenphp:1-php8.3 AS frankenphp_base

SHELL ["/bin/bash", "-euxo", "pipefail", "-c"]

WORKDIR /app

RUN <<-EOF
	apt-get update
	apt-get install -y --no-install-recommends \
		file \
		git
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip \
		pdo_mysql \
		mongodb
	rm -rf /var/lib/apt/lists/*
EOF

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/app.conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --link frankenphp/Caddyfile /etc/frankenphp/Caddyfile

ENTRYPOINT ["docker-entrypoint"]

CMD [ "frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile" ]

# --- Image de production --------------------------------------------------

FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/app.conf.d/

# Les vendors sont installés avant de copier le reste des sources : une
# modification du code n'oblige pas à les réinstaller à chaque build.
COPY --link composer.* symfony.* ./
RUN composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --link . ./

RUN <<-EOF
	mkdir -p var/cache var/log var/share
	composer dump-autoload --classmap-authoritative --no-dev
	composer run-script --no-dev post-install-cmd
	php bin/console asset-map:compile
	chmod +x bin/console
	sync
EOF
