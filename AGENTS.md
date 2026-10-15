# Project Guidelines & Rules

## Git Workflow & Branching Strategy

### 1. Strictly Protected Branches
- **`main`** and **`develop`** are protected branches.
- **NEVER** commit or push directly to `main` or `develop`.
- Direct pushes or commits to `main` and `develop` are strictly prohibited under any circumstances, even for minor 1–2 line fixes.

### 2. Feature & Fix Branch Rule
- **ALL** changes (features, bug fixes, hotfixes, infrastructure/config adjustments) must be developed in a dedicated branch:
  - `feature/<name>` (e.g. `feature/SCRUM-124-AI-moderation`, `feature/mercure-pin-v0.21`)
- Workflow steps:
  1. Branch off from the latest `develop`.
  2. Implement changes and verify locally with tests and linters:
     - `docker compose exec php vendor/bin/phpunit`
     - `docker compose exec php vendor/bin/phpstan analyse --memory-limit=512M`
     - `docker compose exec php vendor/bin/php-cs-fixer fix --dry-run --diff`
  3. Commit only to the `feature/*` branch.
  4. Push the `feature/*` branch to `origin`.
  5. Open a Pull Request: `feature/*` -> `develop`.
  6. Wait for human review and approval by the repo owner (@CPAprince).
  7. Only merge into `develop` via Pull Request after approval.

### 3. Production Release Rule
- Changes reach `main` strictly via a Pull Request from `develop` -> `main`.

## Language & Documentation Standards

### 1. English Only for Repository Content
- **Documentation**: All documentation files (`.md`, guides, architectural documents, technical specs) committed to the repository MUST be written in **English**.
- **Code Comments**: All code comments, PHPDoc / docblocks, variable names, and commit messages MUST be written in **English**.
- **User Communication**: In-chat communication with the developer can remain in Russian/Ukrainian as preferred by the user, but any file written to disk or Git history MUST be in English.

