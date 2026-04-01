#!/bin/bash

. ./install/setup-functions.sh

PLUGIN_SLUG="cf7-vk"

setup-env
configure-nginx

docker-compose -p "${PROJECT_NAME}" up -d

docker-compose -p "${PROJECT_NAME}" exec php sh -c "\
if [ -L ./dev-content/plugins/${PLUGIN_SLUG} ]; then \
    echo 'Removing ${PLUGIN_SLUG} symlink' && \
    rm ./dev-content/plugins/${PLUGIN_SLUG}; \
fi"

echo "Installing root Composer dependencies..."
docker-compose -p "${PROJECT_NAME}" exec php sh -c "composer install"

echo "Preparing content directories..."
docker-compose -p "${PROJECT_NAME}" exec php sh -c "\
mkdir -p ./dev-content ./betas-content ./dev-content/plugins ./betas-content/plugins && \
if [ -d ./wordpress/wp-content ]; then \
    cp -R ./wordpress/wp-content/. ./dev-content/ && \
    cp -R ./wordpress/wp-content/. ./betas-content/ && \
    rm -rf ./wordpress/wp-content; \
fi && \
rm -rf ./dev-content/plugins/${PLUGIN_SLUG}"

echo "Installing plugin Composer dependencies..."
docker-compose -p "${PROJECT_NAME}" exec php sh -c "cd ./plugin-dir && composer install"

echo "Symlinking plugin into dev-content..."
docker-compose -p "${PROJECT_NAME}" exec php sh -c "\
mkdir -p ./dev-content/plugins && \
ln -s /srv/web/plugin-dir /srv/web/dev-content/plugins/${PLUGIN_SLUG}"

echo "Preparing local WordPress bootstrap files..."

[ ! -f ./index.php ] && echo "<?php
define( 'WP_USE_THEMES', true );
require( './wordpress/wp-blog-header.php' );" > ./index.php

if [ ! -f ./_dev-config.php ]; then
  WPCONFIG=$(< ./install/.example/dev-config.php.template)
  printf "$WPCONFIG" $PROJECT_BASE_URL $PROJECT_BASE_URL $DB_NAME $DB_USER $DB_PASSWORD $DB_HOST > ./_dev-config.php
fi

if [ ! -f ./_betas-config.php ]; then
  WPCONFIG=$(< ./install/.example/betas-config.php.template)
  printf "$WPCONFIG" $BETAS_PROJECT_URL $BETAS_PROJECT_URL $DB_NAME $DB_USER $DB_PASSWORD $DB_HOST > ./_betas-config.php
fi

if [ ! -f ./wp-config.php ]; then
  cp ./install/.example/wp-config.template ./wp-config.php
fi

echo "WordPress database init"
echo -n "Would you init a new dev/betas instance? (y/n) "
read -r item

case "$item" in
  y|Y)
    echo "Initializing dev database..."
    docker-compose -p "${PROJECT_NAME}" exec php sh -c "\
    wp db reset --defaults --yes && \
    wp core install --url=${PROJECT_BASE_URL} --title=\"${WP_TITLE}\" --admin_user=${WP_ADMIN} --admin_password=${WP_ADMIN_PASS} --admin_email=${WP_ADMIN_EMAIL} --skip-email && \
    if wp plugin is-installed akismet; then wp plugin delete akismet; fi && \
    if wp plugin is-installed hello; then wp plugin delete hello; fi && \
    wp plugin activate --all"

    fake_posts
    set_permalinks

    sed -i "s/\$current = 'dev';/\$current = 'betas';/" ./wp-config.php

    echo "Initializing betas database..."
    docker-compose -p "${PROJECT_NAME}" exec php sh -c "\
    wp core install --url=${BETAS_PROJECT_URL} --title=\"${BETAS_WP_TITLE}\" --admin_user=${WP_ADMIN} --admin_password=${WP_ADMIN_PASS} --admin_email=${WP_ADMIN_EMAIL} --skip-email && \
    if wp plugin is-installed akismet; then wp plugin delete akismet; fi && \
    if wp plugin is-installed hello; then wp plugin delete hello; fi && \
    wp plugin activate --all"

    set_permalinks

    sed -i "s/\$current = 'betas';/\$current = 'dev';/" ./wp-config.php

    draw_line
    printf "WordPress credentials:\n"
    printf "WP User Admin: %s\nWP User Pass: %s\n" "$WP_ADMIN" "$WP_ADMIN_PASS"

    printf "\nPostman ApplicationApiKey value:\n"
    docker-compose -p "${PROJECT_NAME}" exec php sh -c "wp user application-password create 1 postman --porcelain"
    draw_line
    ;;
  *)
    echo "WP database has not been touched."
    ;;
esac

printf "Do not forget to update /etc/hosts with:\n"
printf "127.0.0.1 %s\n" "${PROJECT_BASE_URL}"
printf "127.0.0.1 %s\n" "${BETAS_PROJECT_URL}"
echo "Done."
