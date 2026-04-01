setup.all:
	bash ./install/setup.sh

setup.env:
	bash ./install/setup-env.sh

docker.up:
	docker-compose -p cf7vk up -d

docker.down:
	docker-compose -p cf7vk down

docker.build.php:
	docker-compose -p cf7vk up -d --build php

clear.all:
	bash ./install/clear.sh

npm.build:
	docker-compose -p cf7vk exec node bash -c "cd ./plugin-dir/react && npm run dev-build"

php.connect:
	docker-compose -p cf7vk exec php bash

php.connect.root:
	docker-compose -p cf7vk exec --user=root php bash

node.connect:
	docker-compose -p cf7vk exec node bash

node.connect.root:
	docker-compose -p cf7vk exec --user=root node bash

php.log:
	docker-compose -p cf7vk exec php sh -c 'tail -n 50 -f /var/log/php/error.log'

i18n.make.json:
ifeq ($(origin LOCALE), undefined)
	@echo "Specify the LOCALE variable. Example: make i18n.make.json LOCALE=ru_RU"
else
	@docker-compose -p cf7vk exec php sh -c '\
		cd ./plugin-dir && \
		wp i18n make-json ./languages/cf7-vk-$(LOCALE).po --no-purge'
endif
