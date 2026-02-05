<?php

declare(strict_types=1);

namespace Twitter\IAM\Domain\Auth\Exception;

use Exception;

final class ValidationErrorException extends Exception
{
    public const ERROR_CODE = 'VALIDATION_ERROR';

    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
