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

## Moderation Architecture

The application includes an AI-driven tweet moderation pipeline designed for high throughput and zero user-facing latency.

### Modes (`MODERATION_MODE` in `.env` / `.env.local`)
- **`bypass` (default)**: Moderation is turned off. All created/updated tweets are approved synchronously and become visible immediately.
- **`demo`**: Simulates moderation locally without calling external APIs (approves ~95% of tweets, rejects ~5% randomly; configurable via `MODERATION_DEMO_APPROVE_PERCENT`).
- **`live`**: Asynchronous moderation using OpenAI's `omni-moderation-latest` model. Requires a valid `OPENAI_API_KEY` set in `.env.local`.

### Worker Service
When running in `demo` or `live` mode, a dedicated background worker polls for pending tweets and processes them in batches:
- Managed automatically via the `moderation-worker` container in `compose.yaml` / `compose.prod.yaml`.
- Can also be started manually:
  ```shell
  docker compose exec php bin/console app:moderation:flush-worker
  ```
- Test provider connectivity and moderation results:
  ```shell
  docker compose exec php bin/console app:moderation:test-provider
  ```

### Frontend Pending Behaviour
- When an author posts or updates a tweet in `demo` or `live` mode, the tweet is saved as `PENDING`.
- The author immediately sees their pending tweet in their feed with an **Under review** badge.
- Other users only see `APPROVED` tweets; `PENDING` and `REJECTED` tweets are hidden from public and author feeds. Likes cannot be placed on non-approved tweets.
- The client periodically polls `GET /api/tweets/{id}/moderation-status` (rate-limited at 30 req/min per user) to detect approval or rejection without full page reloads.
- Stored pending tweets in `localStorage` are automatically cleaned up when the user logs out or when tweets are approved/rejected.

### OpenAI Rate Limits & Tuning
Configured with safety margins (~5%) for OpenAI account tiers:
- **Build tier (default)**: `MODERATION_LIMIT_RPM=475`, `MODERATION_LIMIT_TPM=19000`, `MODERATION_LIMIT_RPD=0` (0 = no daily limit).
- **Free tier**: `MODERATION_LIMIT_RPM=240`, `MODERATION_LIMIT_TPM=9500`, `MODERATION_LIMIT_RPD=4900`.
- Rate limiting is enforced atomically in Redis via Lua scripts, with automatic backoff and support for HTTP 429 `Retry-After` response headers.

*You also can import [Demo Dataset with 555 users, 21k tweets, 79k likes](docs/Dump4tables_for_import_owerwrite_ready.sql)*


## License

Twitter is available under the MIT license. See [LICENSE](LICENSE) for more info.
