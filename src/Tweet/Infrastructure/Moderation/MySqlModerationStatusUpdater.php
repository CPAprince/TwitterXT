<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Moderation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Twitter\Shared\Infrastructure\Persistence\Doctrine\UuidBinaryConverter;
use Twitter\Tweet\Application\Moderation\ModerationDecision;
use Twitter\Tweet\Application\Moderation\ModerationStatusUpdaterInterface;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;

final readonly class MySqlModerationStatusUpdater implements ModerationStatusUpdaterInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    /**
     * @param list<ModerationDecision> $decisions
     *
     * @return list<ModerationDecision>
     */
    public function apply(array $decisions): array
    {
        if ([] === $decisions) {
            return [];
        }

        $sql = '
        UPDATE tweets
        SET moderation_status = ?,
            moderated_at = UTC_TIMESTAMP()
        WHERE id = ?
          AND moderation_version = ?
          AND moderation_status = ?
    ';

        $applied = [];

        foreach ($decisions as $decision) {
            $affected = $this->connection->executeStatement(
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

            if ($affected > 0) {
                $applied[] = $decision;
            }
        }

        return $applied;
    }

    /**
     * @param list<string> $tweetIds
     */
    public function markFailed(array $tweetIds): void
    {
        if ([] === $tweetIds) {
            return;
        }

        $sql = '
            UPDATE tweets
            SET moderation_status = ?,
                moderated_at = UTC_TIMESTAMP()
            WHERE id = ?
              AND moderation_status = ?
        ';

        foreach ($tweetIds as $tweetId) {
            $this->connection->executeStatement(
                $sql,
                [
                    Tweet::MODERATION_FAILED,
                    UuidBinaryConverter::toBytes($tweetId),
                    Tweet::MODERATION_PENDING,
                ],
                [
                    ParameterType::INTEGER,
                    ParameterType::BINARY,
                    ParameterType::INTEGER,
                ]
            );
        }
    }
}
