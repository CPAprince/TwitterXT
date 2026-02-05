<?php

declare(strict_types=1);

namespace Twitter\Like\Application\UseCase\ToggleLike;

final readonly class ToggleLikeCommand
{
    public function __construct(
        public string $tweetId,
        public string $userId,
    ) {}
}
