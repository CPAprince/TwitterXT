<?php

declare(strict_types=1);

namespace Twitter\Tweet\Domain\Tweet\Exception;

use Exception;

final class TweetAccessDeniedException extends Exception
{
    public function __construct(string $userId)
    {
        parent::__construct('Tweet access denied for user with id "'.$userId.'"');
    }
}
