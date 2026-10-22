<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Persistence\MySQL\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add moderation_reason column to tweets table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            ADD moderation_reason VARCHAR(255) DEFAULT NULL;
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            DROP COLUMN moderation_reason;
            SQL
        );
    }
}
