<?php

declare(strict_types=1);

namespace Twitter\Like\Infrastructure\Lock\Exception;

use Exception;

final class LikeActionLockedException extends Exception
{
    public const string ERROR_CODE = 'LIKE_ACTION_LOCKED';

    public function __construct(
        string $tweetId,
        string $userId,
    ) {
        parent::__construct(
            sprintf(
                'Like action for tweet "%s" is LOCKED by user "%s" is already being processed.',
                $tweetId,
                $userId,
            ),
        );
    }
}
