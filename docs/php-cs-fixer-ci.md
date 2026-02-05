# PHP-CS-Fixer in CI (GitHub)

This document describes how **PHP-CS-Fixer** is used in this project:
- locally by developers
- in GitHub Actions CI

---

## Scope

- **Dev (local)**: run CS Fixer manually via Composer scripts.
- **CI (GitHub)**: run CS check in dry-run mode on each push / pull request.

---

## Requirements

1. PHP 8.4 is available.
2. Composer dependencies installed:

   ```bash
   composer install --no-interaction --prefer-dist
