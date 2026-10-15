<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Moderation;

use Redis;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationQueueInterface;

final readonly class RedisModerationQueue implements ModerationQueueInterface
{
    public function __construct(
        private Redis $redis,
        private ModerationConfig $config,
    ) {}

    public function enqueue(string $tweetId): void
    {
        $this->redis->rPush($this->config->redisQueueKey, $tweetId);
    }

    public function peek(int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $items = $this->redis->lRange(
            $this->config->redisQueueKey,
            0,
            $limit - 1
        );

        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (mixed $item): string => is_string($item) ? trim($item) : '',
                $items
            ),
            static fn (string $item): bool => '' !== $item
        ));
    }

    public function remove(array $tweetIds): void
    {
        if ([] === $tweetIds) {
            return;
        }

        foreach ($tweetIds as $tweetId) {
            $this->redis->lRem($this->config->redisQueueKey, $tweetId, 1);
        }
    }

    public function size(): int
    {
        return $this->redis->lLen($this->config->redisQueueKey);
    }
}
