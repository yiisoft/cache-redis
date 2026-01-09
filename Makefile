help:			## Display help information
	@fgrep -h "##" $(MAKEFILE_LIST) | fgrep -v fgrep | sed -e 's/\\$$//' | sed -e 's/##//'

build:			## Build an image from a docker-compose file. Params: {{ v=8.1 }}. Default latest PHP 8.1
	PHP_VERSION=$(filter-out $@,$(v)) docker compose -f tests/docker/docker-compose.yml up -d --build

down:			## Stop and remove containers, networks
	docker compose -f tests/docker/docker-compose.yml down

start:			## Start services
	docker compose -f tests/docker/docker-compose.yml up -d

sh:			## Enter the container with the application
	docker exec -it cache-redis-php sh

test:			## Run tests. Params: {{ v=8.1 }}. Default latest PHP 8.1
	PHP_VERSION=$(filter-out $@,$(v)) docker compose -f tests/docker/docker-compose.yml build --pull cache-redis-php
	make create-cluster
	PHP_VERSION=$(filter-out $@,$(v)) docker compose -f tests/docker/docker-compose.yml run cache-redis-php vendor/bin/phpunit --colors=always
	make down

create-cluster:		## Create Redis cluster
	docker exec redis1 sh -c "redis-cli -p 6381 -a Password --cluster create 172.20.128.2:6381 172.20.128.3:6382 172.20.128.4:6383 172.20.128.5:6384 172.20.128.6:6385 172.20.128.7:6386 --cluster-replicas 1 --no-auth-warning --cluster-yes"

connect-cluster:	## Connect to Redis cluster
	docker exec -it redis1 sh -c "redis-cli -c -p 6381 -a Password --no-auth-warning"

mutation-test:		## Run mutation tests. Params: {{ v=8.1 }}. Default latest PHP 8.1
	PHP_VERSION=$(filter-out $@,$(v)) docker compose -f tests/docker/docker-compose.yml build --pull cache-redis-php
	make create-cluster
	PHP_VERSION=$(filter-out $@,$(v)) docker compose -f tests/docker/docker-compose.yml run cache-redis-php php -dpcov.enabled=1 -dpcov.directory=. vendor/bin/roave-infection-static-analysis-plugin --threads=2 --ignore-msi-with-no-mutations --only-covered
	make down

coverage:		## Run code coverage. Params: {{ v=8.1 }}. Default latest PHP 8.1
	PHP_VERSION=$(filter-out $@,$(v)) docker compose -f tests/docker/docker-compose.yml run cache-redis-php vendor/bin/phpunit --coverage-clover coverage.xml
	make down

static-analyze:		## Run code static analyze. Params: {{ v=8.1 }}. Default latest PHP 8.1
	PHP_VERSION=$(filter-out $@,$(v)) docker compose -f tests/docker/docker-compose.yml run cache-redis-php vendor/bin/psalm --config=psalm.xml --stats --php-version=$(v)
	make down
