# Twitter | EPAM Campus

This project is a Twitter-like application developed for EPAM Campus.

All detailed documentation → [/docs](docs)

## Quick start

Clone the repository and check out the development branch

```sh
git clone https://github.com/CPAprince/TwitterXT.git
git checkout develop
git pull
```

Build application image and launch Docker containers

```sh
docker compose build --pull --no-cache
docker compose up --wait
```

Assuming that you already have [Composer](http://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos)
installed globally, install required dependencies

```shell
docker compose exec php composer install --no-interaction
```

Run migrations

```shell
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

Generate keys for JWT
```shell
docker compose exec php php bin/console lexik:jwt:generate-keypair
```

Open  https://localhost



*You also can import [Demo Dataset with 555 users, 21k tweets, 79k likes](docs/Dump4tables_for_import_owerwrite_ready.sql)*


## License

Twitter is available under the MIT license. See [LICENSE](LICENSE) for more info.
