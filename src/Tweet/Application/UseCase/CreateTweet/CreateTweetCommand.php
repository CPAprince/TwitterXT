<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\CreateTweet;

final readonly class CreateTweetCommand
{
    public function __construct(
        public string $userId,
        public string $content,
    ) {}
}
