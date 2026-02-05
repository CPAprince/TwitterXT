<?php

declare(strict_types=1);

namespace Twitter\Profile\Domain\Profile\Exception;

use Exception;

final class ProfileAlreadyExistsException extends Exception
{
    public function __construct(string $userId)
    {
        parent::__construct('Profile already exists for user with id "'.$userId.'"');
    }
}
