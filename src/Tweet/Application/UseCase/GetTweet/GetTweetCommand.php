<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\GetTweet;

final readonly class GetTweetCommand
{
    public function __construct(public string $tweetId) {}
}
