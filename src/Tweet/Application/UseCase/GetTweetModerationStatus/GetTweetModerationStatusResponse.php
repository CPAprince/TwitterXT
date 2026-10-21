<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\GetTweetModerationStatus;

final readonly class GetTweetModerationStatusResponse
{
    public function __construct(
        public string $tweetId,
        public string $status,
    ) {}
}
