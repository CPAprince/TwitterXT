<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Persistence\MySQL\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261021200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tweet moderation status, timestamps, version and replace feed indexes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            DROP INDEX idx_tweets_created_id;
            SQL
        );

        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            ADD moderation_status SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            ADD moderated_at DATETIME DEFAULT NULL,
            ADD moderation_version INT UNSIGNED NOT NULL DEFAULT 1;
            SQL
        );

        $this->addSql(
            <<<SQL
            UPDATE tweets
            SET moderation_status = 1,
                moderated_at = created_at
            WHERE moderation_status = 0;
            SQL
        );

        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            ADD INDEX idx_tweets_created_id (moderation_status, created_at DESC, id DESC);
            SQL
        );

        // Keep a temporary index on user_id so InnoDB does not reject dropping idx_tweets_user_created_id due to foreign key
        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            ADD INDEX idx_tweets_user_fk (user_id),
            DROP INDEX idx_tweets_user_created_id;
            SQL
        );

        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            ADD INDEX idx_tweets_user_created_id (user_id, moderation_status, created_at DESC, id DESC),
            DROP INDEX idx_tweets_user_fk;
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            DROP INDEX idx_tweets_created_id;
            SQL
        );

        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            ADD INDEX idx_tweets_user_fk (user_id),
            DROP INDEX idx_tweets_user_created_id;
            SQL
        );

        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            DROP COLUMN moderation_status,
            DROP COLUMN moderated_at,
            DROP COLUMN moderation_version;
            SQL
        );

        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            ADD INDEX idx_tweets_created_id (created_at DESC, id DESC);
            SQL
        );

        $this->addSql(
            <<<SQL
            ALTER TABLE tweets
            ADD INDEX idx_tweets_user_created_id (user_id, created_at DESC, id DESC),
            DROP INDEX idx_tweets_user_fk;
            SQL
        );
    }
}
