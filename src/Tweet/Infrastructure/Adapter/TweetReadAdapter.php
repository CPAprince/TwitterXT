<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Adapter;

use Doctrine\DBAL\Connection;
use Twitter\Shared\Infrastructure\Persistence\Doctrine\UuidBinaryConverter;
use Twitter\Tweet\Application\Port\TweetReadPortInterface;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;

final readonly class TweetReadAdapter implements TweetReadPortInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function isApproved(string $tweetId): bool
    {
        $sql = '
            SELECT moderation_status
            FROM tweets
            WHERE id = ?
        ';

        $status = $this->connection->fetchOne(
            $sql,
            [UuidBinaryConverter::toBytes($tweetId)]
        );

        if (false === $status || null === $status) {
            return false;
        }

        return Tweet::MODERATION_APPROVED === (int) $status;
    }
}
