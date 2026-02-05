# How to Create and Map Doctrine Entities

## Define an entity

Entities are located in `Entity` directory of corresponding modules.
Let's create a simple health check report entity `src/HealthCheck/Entity/HealthCheckReport.php`:

```php
final class HealthCheckReport
{
    public readonly ?int $id;
    public HealthCheckReportStatus $status;
    public readonly \DateTimeImmutable $createdAt;

    public function __construct(HealthCheckReportStatus $status = HealthCheckReportStatus::HEALTHY)
    {
        $this->id = null;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
    }
}
```

And `src/HealthCheck/Entity/HealthCheckReportStatus.php`:

```php
enum HealthCheckReportStatus: string
{
    case HEALTHY = 'application services are healthy';
    case DATABASE_UNREACHABLE = 'could not ping the database'
}
```

## Doctrine XML mapping

### Create an XML file

Doctrine entities corresponding `.orm.xml` files are located in [`config/doctrine`](../config/doctrine) directory.
The mapping filename has the following structure: `<ModuleName>.<EntityDirectiry>.<EntityClassName>.orm.xml`.
In our case we can create `../config/doctrine/HealthCheck.Entity.HealthCheckReport.orm.xml`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<doctrine-mapping xmlns="http://doctrine-project.org/schemas/orm/doctrine-mapping"
                  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xsi:schemaLocation="
                    http://doctrine-project.org/schemas/orm/doctrine-mapping
                    https://www.doctrine-project.org/schemas/orm/doctrine-mapping.xsd">

    <entity name="Twitter\HealthCheck\Entity\HealthCheckReport" table="health_check_reports">

        <id name="id" type="integer">
            <generator strategy="AUTO"/>
        </id>

        <field name="status" type="enum"/>
        <field name="createdAt" type="datetime_immutable"/>

    </entity>

</doctrine-mapping>

```

### Update Doctrine schema

```shell
# Validate XML mappings
docker compose exec php php bin/console doctrine:schema:validate
# Create required migrations based on current database schema
docker compose exec php php bin/console doctrine:migrations:diff

docker compose exec php php bin/console doctrine:migrations:migrate
docker compose exec php php bin/console doctrine:migrations:up-to-date
```
