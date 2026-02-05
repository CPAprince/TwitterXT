<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\GetTweets;

use Twitter\Tweet\Application\UseCase\Shared\TweetResponse;

final readonly class GetTweetsResponse
{
    /** @param list<TweetResponse> $tweets */
    public function __construct(public array $tweets) {}
}
