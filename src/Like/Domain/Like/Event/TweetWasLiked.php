<?php

declare(strict_types=1);

namespace Twitter\Like\Domain\Like\Event;

final readonly class TweetWasLiked
{
    public function __construct(
        public string $tweetId,
        public string $userId,
    ) {}
}
