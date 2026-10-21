<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

final readonly class ModerationBatchItem
{
    public function __construct(
        public string $tweetId,
        public string $text,
        public int $estimatedTokens,
        public int $moderationVersion,
    ) {}
}
