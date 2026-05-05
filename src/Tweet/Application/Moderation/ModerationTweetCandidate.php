<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

final readonly class ModerationTweetCandidate
{
    public function __construct(
        public string $tweetId,
        public string $text,
        public int $moderationVersion,
    ) {}
}
