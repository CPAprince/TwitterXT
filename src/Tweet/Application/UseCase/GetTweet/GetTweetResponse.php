<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\GetTweet;

use DateTimeImmutable;

final readonly class GetTweetResponse
{
    public function __construct(
        public string $id,
        public string $content,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public string $authorId,
        public string $authorName,
    ) {}
}
