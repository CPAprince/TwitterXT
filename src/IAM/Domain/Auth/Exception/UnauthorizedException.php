<?php

declare(strict_types=1);

namespace Twitter\IAM\Domain\Auth\Exception;

use Exception;

final class UnauthorizedException extends Exception
{
    public const ERROR_CODE = 'AUTH_UNAUTHORIZED';

    public function __construct(string $message = 'User is not authenticated.')
    {
        parent::__construct($message);
    }
}
