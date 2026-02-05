<?php

declare(strict_types=1);

namespace Twitter\Like\Domain\Like\Exception;

use Exception;

final class LikeAlreadyExistsException extends Exception
{
    public const string ERROR_CODE = 'LIKE_ALREADY_EXISTS';

    public function __construct(
        string $tweetId,
        string $userId,
    ) {
        parent::__construct(
            sprintf(
                'Like for tweet "%s" by user "%s" already exists.',
                $tweetId,
                $userId,
            ),
        );
    }
}
