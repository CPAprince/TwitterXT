# PHPStan – Static Analysis
https://phpstan.org/user-guide/getting-started

This project uses **PHPStan** for static analysis with full
**Symfony** and **Doctrine** integration.

> **Scope:**  
> This guide applies to the **development environment** running inside Docker.  
> Production/CI-specific configuration should be documented separately  
> (e.g. `docs/phpstan-ci.md`).

---

## Installation & Usage (dev)

PHPStan is executed via a helper script which:

1. Ensures the Docker `php` container is running.
2. Checks if the required PHPStan packages are installed:
    - `phpstan/phpstan`
    - `phpstan/phpstan-symfony`
    - `phpstan/phpstan-doctrine`
3. Installs any missing dev dependencies.
4. Ensures Symfony’s container XML exists  
   (`var/cache/dev/App_KernelDevDebugContainer.xml`), warming up cache only if needed.
5. Runs PHPStan using your `composer phpstan` script.

---

## Windows support

This script requires a **Bash** shell.

Supported environments:

- **macOS** — works out of the box
- **Linux** — works out of the box
- **Windows (WSL2)** — fully supported, recommended
- **Windows (Git Bash)** — supported; run using:

  ```bash
  tools/phpstan.sh
