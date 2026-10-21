<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

interface ModerationQueueInterface
{
    public function enqueue(string $tweetId): void;

    /**
     * @return string[]
     */
    public function peek(int $limit): array;

    /**
     * @param string[] $tweetIds
     */
    public function remove(array $tweetIds): void;

    public function size(): int;
}
