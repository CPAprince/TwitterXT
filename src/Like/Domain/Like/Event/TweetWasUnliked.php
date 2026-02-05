<?php

declare(strict_types=1);

namespace Twitter\Like\Domain\Like\Event;

final readonly class TweetWasUnliked
{
    public function __construct(
        public string $tweetId,
        public string $userId,
    ) {}
}
