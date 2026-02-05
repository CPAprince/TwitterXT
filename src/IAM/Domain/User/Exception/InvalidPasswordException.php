<?php

declare(strict_types=1);

namespace Twitter\IAM\Domain\User\Exception;

use Exception;

final class InvalidPasswordException extends Exception
{
    public function __construct(string $message = 'Invalid password')
    {
        parent::__construct($message);
    }
}
