# Twitter | EPAM Campus

This project is a Twitter-like application developed for EPAM Campus.

All detailed documentation → [/docs](docs)  
Load testing guide & scenarios → [/k6/k6.md](k6/k6.md)

## Quick start

Clone the repository and check out the development branch

```sh
git clone https://github.com/CPAprince/Twitter.git
git checkout develop
git pull
```

Build application image and launch Docker containers (Development mode)

```sh
make startdev
# or simply: make start
```

To stop containers at any time:
```sh
make stop
```

### Environments & k6 Load Testing

Switching between Development and Production environments is managed via `Makefile`:

#### Run in Production Mode for k6 Load Testing:
Runs FrankenPHP in optimized production mode (`APP_ENV=prod`, `APP_DEBUG=0`, OPcache enabled, 5 persistent workers, Symfony WebProfiler disabled):
```sh
# Stop running dev containers:
make stop

# Build and start production stack:
make startprod

# Execute k6 load test scenario (see k6/k6.md for details):
k6 run k6/TwitterGPTtest5sql.js

# Return to development mode after testing:
make stop
make startdev
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

# Moderation

Set moderation mode in `.env`:
- `bypass` (default): moderation is disabled, all tweets approved automatically.
- `demo`: simulate moderation without external API calls (random rejections for testing).
- `live`: real OpenAI moderation via `omni-moderation-latest` (requires `OPENAI_API_KEY` in `.env.local`).

Moderation worker command:
```shell
docker compose exec php php bin/console app:moderation:flush-worker
```

*You also can import [Demo Dataset with 555 users, 21k tweets, 79k likes](docs/Dump4tables_for_import_owerwrite_ready.sql)*


## License

Twitter is available under the MIT license. See [LICENSE](LICENSE) for more info.
