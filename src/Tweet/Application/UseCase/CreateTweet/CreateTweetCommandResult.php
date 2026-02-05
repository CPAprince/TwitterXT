<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\CreateTweet;

use DateTimeImmutable;

final readonly class CreateTweetCommandResult
{
    public function __construct(
        public string $tweetId,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
