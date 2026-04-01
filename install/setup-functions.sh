#!/bin/bash

draw_line(){
  printf %"$(tput cols)"s |tr " " "-"
}

fake_posts(){
  docker-compose -p "${PROJECT_NAME}" exec php sh -c "\
  wp post create --post_type=cf7vk_chat --post_title=\"Chat 0\" --post_status=publish && \
  wp post create --post_type=cf7vk_chat --post_title=\"Chat 1\" --post_status=publish && \
  wp post create --post_type=cf7vk_bot --post_title=\"Bot example\" --post_status=publish && \
  wp post create --post_type=cf7vk_channel --post_title=\"Channel 0\" --post_status=publish && \
  wp post create --post_type=cf7vk_channel --post_title=\"Channel 1\" --post_status=publish && \
  wp post create --post_type=cf7vk_channel --post_title=\"Channel 2\" --post_status=publish"
}

set_permalinks(){
  docker-compose -p "${PROJECT_NAME}" exec php sh -c "wp rewrite structure '/%year%/%monthnum%/%postname%/'"
}

setup-env(){
  echo "Create .env from example"
  if [ ! -f ./.env ]; then
      echo "File .env doesn't exist. Recreating..."
      cp ./install/.example/.env.example ./.env && echo "Ok."
  else
      echo "File .env already exists."
  fi

  . ./.env
}

configure-nginx() {
  echo "Configuring nginx ..."
  [ ! -d ./install/nginx/ ] && mkdir -p ./install/nginx/ && cp -R ./install/.example/ssl ./install/nginx/

  if [ ! -f ./install/nginx/dev.conf ]; then
    NGINXCONFIG=$(< ./install/.example/nginx.conf.template)
    printf "$NGINXCONFIG" $PROJECT_BASE_URL $PROJECT_BASE_URL dev $PROJECT_BASE_URL $PROJECT_BASE_URL > ./install/nginx/dev.conf
  fi

  if [ ! -f ./install/nginx/betas.conf ]; then
    NGINXCONFIG=$(< ./install/.example/nginx.conf.template)
    printf "$NGINXCONFIG" $BETAS_PROJECT_URL $BETAS_PROJECT_URL betas $BETAS_PROJECT_URL $BETAS_PROJECT_URL > ./install/nginx/betas.conf
  fi

  touch install/nginx/access.log
  touch install/php-fpm/error.log
}
