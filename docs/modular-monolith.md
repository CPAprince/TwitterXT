# Modular Monolith Architecture

This project is a **modular monolith** built with [**hexagonal architecture**](https://w.wiki/GbES) and
[**CQRS**](https://w.wiki/GbET). Each business function is implemented as an isolated **module** under `src/`, while
`src/Shared` contains cross-cutting building blocks (e.g., common value objects, utilities, and abstractions).

At a high level:

- **Modules** encapsulate a single business domain (e.g. IAM, Billing).
- **Domain** code is framework-agnostic and contains the core business logic.
- **Application** code exposes use cases (commands/queries) and orchestrates the domain.
- **UI** and **Infrastructure** adapt the application and domain to the outside world (HTTP, database, etc.).

## Project Structure

```text
src/
├── Shared/
└── <Module>/
    ├── UI/
    │   ├── REST/   # Controllers, event subscribers, request/response objects, etc.
    │   └── Web/    # Web-specific UI (e.g., templates, controllers, view models, etc.)
    ├── Domain/
    │   └── <Model>/    # Entities, value objects, repository interfaces, etc.
    ├── Application/
    │   └── <UseCase>/
    └── Infrastructure/
        └── Persistence/
            ├── Doctrine/
            │   └── Mapping/    # Doctrine XML mapping files
            └── MySQL/
                ├── Migration/  # Database migrations for this module
                └── Repository/ # Concrete repository implementations, DB gateways, etc.
```

Here is an example of how the IAM (Identity and Access Management) module may look:

```text
src/
├── Shared/
└── IAM/
    ├── UI/
    │   ├── REST/
    │   │   ├── LoginController.php
    │   │   ├── LogoutController.php
    │   │   └── RegisterController.php
    │   └── Web/
    ├── Domain/
    │   └── User/
    │       └── Model/
    │           ├── User.php
    │           ├── Id.php
    │           ├── Email.php
    │           ├── PasswordHash.php
    │           ├── InvalidIdException.php
    │           ├── InvalidEmailException.php
    │           ├── InvalidPasswordException.php
    │           └── UserRepositoryInterface.php
    ├── Application/
    │   ├── Login/
    │   │   ├── LoginHandler.php
    │   │   ├── LoginCommand.php
    │   │   └── InvalidCredentialsException.php
    │   ├── Logout/
    │   │   ├── LogoutHandler.php
    │   │   └── LogoutCommand.php
    │   └── Register/
    │       ├── RegisterHandler.php
    │       ├── RegisterCommand.php
    │       └── EmailAlreadyUsedException.php
    └── Infrastructure/
        ├── Auth/
        │   ├── Authenticator/
        │   ├── SymfonyUser.php # implements Symfony UserInterface
        │   └── UserProvider.php
        └── Persistence/
            ├── Doctrine/
            │   └── Mapping/
            │       └── *.orm.xml
            └── MySQL/
                ├── Migration/
                │   └── Version*.php
                └── Repository/
                    └── MySQLUserRepository.php  # implements UserRepositoryInterface
``` 
