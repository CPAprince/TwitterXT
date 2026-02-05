<?php

declare(strict_types=1);

namespace Twitter\Profile\Domain\Profile\Exception;

use Exception;

final class ProfileNotFoundException extends Exception
{
    public function __construct(string $userId)
    {
        parent::__construct('Profile not found for user with id "'.$userId.'"');
    }
}
