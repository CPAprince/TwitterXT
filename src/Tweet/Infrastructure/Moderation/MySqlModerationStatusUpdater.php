<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Moderation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Twitter\Shared\Infrastructure\Persistence\Doctrine\UuidBinaryConverter;
use Twitter\Tweet\Application\Moderation\ModerationStatusUpdaterInterface;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;

final readonly class MySqlModerationStatusUpdater implements ModerationStatusUpdaterInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function apply(array $decisions): void
    {
        if ([] === $decisions) {
            return;
        }

        $sql = '
        UPDATE tweets
        SET moderation_status = ?,
            moderated_at = UTC_TIMESTAMP()
        WHERE id = ?
          AND moderation_version = ?
          AND moderation_status = ?
    ';

        foreach ($decisions as $decision) {
            $this->connection->executeStatement(
                $sql,
                [
                    $decision->approved
                        ? Tweet::MODERATION_APPROVED
                        : Tweet::MODERATION_REJECTED,
                    UuidBinaryConverter::toBytes($decision->tweetId),
                    $decision->moderationVersion,
                    Tweet::MODERATION_PENDING,
                ],
                [
                    ParameterType::INTEGER,
                    ParameterType::BINARY,
                    ParameterType::INTEGER,
                    ParameterType::INTEGER,
                ]
            );
        }
    }
}
