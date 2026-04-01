ARG PHP_VER

FROM wodby/wordpress-php:${PHP_VER}-4.62.1

ARG WP_CLI_VERSION=2.12.0

LABEL maintaner="Igor Tron <public@itron.pro>"

WORKDIR /srv/web

USER root

RUN mkdir -p /var/log/php && chown -R wodby:wodby /var/log/php
RUN echo 'alias ll="ls -l --color"' >> /etc/bash.bashrc
RUN echo 'alias ll="ls -l --color"' >> /home/wodby/.bashrc
RUN curl -fsSL -o /usr/local/bin/wp "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar" \
    && chmod +x /usr/local/bin/wp

USER wodby
