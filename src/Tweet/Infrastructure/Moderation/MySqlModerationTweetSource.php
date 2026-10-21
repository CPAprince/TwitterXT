<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Moderation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Twitter\Shared\Infrastructure\Persistence\Doctrine\UuidBinaryConverter;
use Twitter\Tweet\Application\Moderation\ModerationTweetCandidate;
use Twitter\Tweet\Application\Moderation\ModerationTweetSourceInterface;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;

final readonly class MySqlModerationTweetSource implements ModerationTweetSourceInterface
{
    public function __construct(
        private Connection $connection,
    ) {}

    public function findByIds(array $tweetIds): array
    {
        if ([] === $tweetIds) {
            return [];
        }

        $binaryIds = array_map(
            static fn (string $id): string => UuidBinaryConverter::toBytes($id),
            $tweetIds
        );

        $placeholders = implode(', ', array_fill(0, count($binaryIds), '?'));

        $sql = sprintf(
            'SELECT BIN_TO_UUID(id) AS id, BIN_TO_UUID(user_id) AS user_id, content, moderation_version
             FROM tweets
             WHERE id IN (%s)
               AND moderation_status = ?',
            $placeholders
        );

        $params = [...$binaryIds, Tweet::MODERATION_PENDING];
        $types = array_fill(0, count($binaryIds), ParameterType::BINARY);
        $types[] = ParameterType::INTEGER;

        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);

        if ([] === $rows) {
            return [];
        }

        $candidates = [];

        foreach ($rows as $row) {
            $tweetId = (string) $row['id'];
            $userId = (string) ($row['user_id'] ?? '');
            $content = (string) $row['content'];
            $moderationVersion = (int) $row['moderation_version'];

            $candidates[] = new ModerationTweetCandidate(
                tweetId: $tweetId,
                text: $content,
                moderationVersion: $moderationVersion,
                userId: $userId,
            );
        }

        return $this->sortByRequestedIdsOrder($candidates, $tweetIds);
    }

    /**
     * @return list<ModerationTweetCandidate>
     */
    public function findPending(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $sql = '
            SELECT BIN_TO_UUID(id) AS id, BIN_TO_UUID(user_id) AS user_id, content, moderation_version
            FROM tweets
            WHERE moderation_status = ?
            ORDER BY created_at, id
            LIMIT '.(int) $limit;

        $rows = $this->connection->fetchAllAssociative(
            $sql,
            [Tweet::MODERATION_PENDING],
            [ParameterType::INTEGER]
        );

        if ([] === $rows) {
            return [];
        }

        $candidates = [];

        foreach ($rows as $row) {
            $tweetId = (string) $row['id'];
            $userId = (string) ($row['user_id'] ?? '');
            $content = (string) $row['content'];
            $moderationVersion = (int) $row['moderation_version'];

            $candidates[] = new ModerationTweetCandidate(
                tweetId: $tweetId,
                text: $content,
                moderationVersion: $moderationVersion,
                userId: $userId,
            );
        }

        return $candidates;
    }

    /**
     * @param ModerationTweetCandidate[] $candidates
     * @param string[]                   $requestedIds
     *
     * @return ModerationTweetCandidate[]
     */
    private function sortByRequestedIdsOrder(array $candidates, array $requestedIds): array
    {
        $orderMap = [];

        foreach ($requestedIds as $index => $tweetId) {
            $orderMap[$tweetId] = $index;
        }

        usort(
            $candidates,
            static function (ModerationTweetCandidate $left, ModerationTweetCandidate $right) use ($orderMap): int {
                return ($orderMap[$left->tweetId] ?? PHP_INT_MAX)
                    <=> ($orderMap[$right->tweetId] ?? PHP_INT_MAX);
            }
        );

        return $candidates;
    }
}
