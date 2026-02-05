<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\GetTweets;

final readonly class GetTweetsCommand
{
    public function __construct(
        public int $limit = 100,
        public int $page = 1,
    ) {}
}
