build:
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose up -d --build

down:
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose down

start:
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose up -d

test: test80 test81

test80:
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose build --pull php80
	make create-cluster
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose run php80 vendor/bin/phpunit --colors=always
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose down

test81:
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose build --pull php81
	make create-cluster
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose run php81 vendor/bin/phpunit --colors=always
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose down

create-cluster:
	docker exec redis1 sh -c "redis-cli -p 6381 -a Password --cluster create 172.20.128.2:6381 172.20.128.3:6382 172.20.128.4:6383 172.20.128.5:6384 172.20.128.6:6385 172.20.128.7:6386 --cluster-replicas 1 --no-auth-warning --cluster-yes"

connect-cluster:
	docker exec -it redis1 sh -c "redis-cli -c -p 6381 -a Password --no-auth-warning"

mutation-test:
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose build --pull php$(v)
	make create-cluster
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose run php$(v) php -dpcov.enabled=1 -dpcov.directory=. vendor/bin/roave-infection-static-analysis-plugin -j2 --ignore-msi-with-no-mutations --only-covered
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose down

coverage:
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose run php80 vendor/bin/phpunit --coverage-clover coverage.xml
	COMPOSE_FILE=tests/docker/docker-compose.yml docker-compose down
