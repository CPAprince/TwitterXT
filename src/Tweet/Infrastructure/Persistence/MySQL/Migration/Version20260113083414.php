<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Persistence\MySQL\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260113083414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add likes_count column to tweets table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            ALTER TABLE tweets ADD likes_count INT UNSIGNED DEFAULT 0 NOT NULL
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tweets DROP likes_count');
    }
}
