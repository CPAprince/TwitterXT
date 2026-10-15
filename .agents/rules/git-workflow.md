# Git Workflow & Branching Rules

## Golden Rule
Never commit or push directly to `main` or `develop`. All changes go through `feature/*` branches and Pull Requests.

## Detailed Guidelines
1. **Branching**:
   - Always branch off from the latest `develop`.
   - Branch naming: `feature/<feature-or-fix-name>`.

2. **Local Validation Before Commit**:
   - PHPUnit: `docker compose exec php vendor/bin/phpunit`
   - PHPStan: `docker compose exec php vendor/bin/phpstan analyse --memory-limit=512M`
   - PHP-CS-Fixer: `docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff`

3. **Pull Request Workflow**:
   - Push branch to `origin/feature/<name>`.
   - Create Pull Request: `feature/*` -> `develop`.
   - Merging is done by human review & approval only.
   - Release: `develop` -> `main` via Pull Request only.
