<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Persistence\MySQL\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260408150600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tweets table indexes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            ADD INDEX idx_tweets_created_id (created_at DESC, id DESC);
            SQL
        );

        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            ADD INDEX idx_tweets_user_created_id (user_id, created_at DESC, id DESC);
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            DROP INDEX idx_tweets_created_id,
            DROP INDEX idx_tweets_user_created_id;
            SQL
        );
    }
}
