<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Persistence\MySQL\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add moderation fields and feed indexes to tweets';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('
            ALTER TABLE tweets
            ADD moderation_status TINYINT UNSIGNED NOT NULL DEFAULT 0,
            ADD moderated_at DATETIME DEFAULT NULL,
            ADD moderation_version INT UNSIGNED NOT NULL DEFAULT 1
        ');

        // Mark tweets in table as APPROVED
        $this->addSql('
        UPDATE tweets SET moderation_status = 1 WHERE moderation_status = 0
        ');

        $this->addSql('
            CREATE INDEX idx_tweets_feed_status_created_id
            ON tweets (moderation_status, created_at DESC, id)
        ');

        $this->addSql('
            CREATE INDEX idx_tweets_user_feed_status_created_id
            ON tweets (user_id, moderation_status, created_at DESC, id)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_tweets_feed_status_created_id ON tweets');
        $this->addSql('DROP INDEX idx_tweets_user_feed_status_created_id ON tweets');

        $this->addSql('
            ALTER TABLE tweets
            DROP moderation_status,
            DROP moderated_at,
            DROP moderation_version
        ');
    }
}
