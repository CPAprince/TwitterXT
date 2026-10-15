router:
	docker compose exec php bin/console debug:router

cache-clear:
	docker compose exec php php bin/console cache:clear

stan:
	docker compose exec php php -d memory_limit=1G vendor/bin/phpstan analyse

cs:
	docker compose exec php vendor/bin/php-cs-fixer fix --diff --allow-risky=yes

csdr:
	docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff --allow-risky=yes

test:
	docker compose exec php vendor/bin/phpunit

testdetails:
	docker compose exec php vendor/bin/phpunit --display-all-issues --colors=always

dump-auto:
	docker compose exec php composer dump-autoload

about:
	docker compose exec php php bin/console about

container:
	docker compose exec php php bin/console debug:container

start:
	docker compose up --wait -d

stop:
	docker compose down

startb:
	docker compose up -d --build --wait

worker:
	docker compose exec php php bin/console app:moderation:flush-worker

phpmetrics:
	docker compose exec php ./vendor/bin/phpmetrics --report-html=phpmetrics3 ./src

allchecks: stan csdr testdetails

r: router
cc: cache-clear
s: stan
t: test
td: testdetails
