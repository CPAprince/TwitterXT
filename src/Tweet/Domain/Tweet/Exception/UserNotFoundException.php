<?php

declare(strict_types=1);

namespace Twitter\Tweet\Domain\Tweet\Exception;

use Exception;

final class UserNotFoundException extends Exception
{
    public function __construct(string $userId)
    {
        parent::__construct('User with id "'.$userId.'" not found');
    }
}
