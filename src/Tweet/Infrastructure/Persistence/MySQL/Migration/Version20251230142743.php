<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Persistence\MySQL\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251230142743 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tweets table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            CREATE TABLE tweets (
                user_id BINARY(16) NOT NULL,
                content VARCHAR(280) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                id BINARY(16) NOT NULL,
                PRIMARY KEY (id),
                FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_0900_ai_ci`
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE tweets');
    }
}
