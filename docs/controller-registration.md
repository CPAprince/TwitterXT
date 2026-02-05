# How to Create and Register Controllers

## Create a new controller

Controllers are located in `Controller` directory of corresponding modules,
for example `../src/HealthCheck/Controller/HealthCheckController.php)`.
Each controller class name **must** have `Controller` prefix.

## Register routes

Controller configuration has to be done manually added to [`routes.yaml`](../config/routes.yaml) in the following way:

```yaml
<module_name_in_snake_case>:
    resource: ../src/<ModuleDirectory>/Controller/<ControllerClassName>.php
    type: attribute
```

For example, [`HealthCheck`](../src/HealthCheck) controller:

```yaml
health_check_module:
    resource: ../src/HealthCheck/Controller/HealthCheckController.php
    type: attribute
```
