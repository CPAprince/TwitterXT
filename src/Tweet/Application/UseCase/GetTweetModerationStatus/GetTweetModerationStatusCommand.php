<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\GetTweetModerationStatus;

final readonly class GetTweetModerationStatusCommand
{
    public function __construct(
        public string $tweetId,
        public string $userId,
    ) {}
}
