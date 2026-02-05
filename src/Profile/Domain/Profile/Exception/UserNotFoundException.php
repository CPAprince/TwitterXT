<?php

declare(strict_types=1);

namespace Twitter\Profile\Domain\Profile\Exception;

use Exception;

final class UserNotFoundException extends Exception
{
    public function __construct(string $userId)
    {
        parent::__construct('User with id "'.$userId.'" not found');
    }
}
