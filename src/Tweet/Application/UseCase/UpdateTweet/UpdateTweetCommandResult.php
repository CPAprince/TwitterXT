<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\UpdateTweet;

use DateTimeImmutable;

final readonly class UpdateTweetCommandResult
{
    public function __construct(
        public string $content,
        public DateTimeImmutable $updatedAt,
    ) {}
}
