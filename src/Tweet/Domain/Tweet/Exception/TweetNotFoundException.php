<?php

declare(strict_types=1);

namespace Twitter\Tweet\Domain\Tweet\Exception;

use RuntimeException;

final class TweetNotFoundException extends RuntimeException
{
    public const ERROR_CODE = 'TWEET_NOT_FOUND';

    public function __construct(string $tweetId)
    {
        parent::__construct(sprintf('Tweet "%s" not found.', $tweetId));
    }
}
