<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Port;

interface TweetReadPortInterface
{
    public function isApproved(string $tweetId): bool;
}
