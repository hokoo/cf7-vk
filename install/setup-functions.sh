#!/bin/bash

draw_line(){
  printf %"$(tput cols)"s |tr " " "-"
}

setup-env(){
  echo "Create .env from example"
  if [ ! -f ./.env ]; then
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
