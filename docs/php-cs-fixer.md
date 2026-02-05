# PHP-CS-Fixer – Code Style

This project uses **PHP-CS-Fixer** to automatically enforce a consistent
coding style based on **PSR-12** and **Symfony** standards.

PHP-CS-Fixer must NOT be installed or executed in the production environment.

https://cs.symfony.com

> **Scope:**  
> This setup is intended for the **development environment** running inside Docker.  
> CI-specific configuration can be added later if needed.

---

## 1. Entry point

Supported environments:

- **macOS** — works out of the box
- **Linux** — works out of the box
- **Windows (WSL2)** — fully supported, recommended
- **Windows (Git Bash)** — supported; run using:

```bash
tools/cs-fixer
```


## 2. Fix
```bash
tools/cs-fixer --fix
```
