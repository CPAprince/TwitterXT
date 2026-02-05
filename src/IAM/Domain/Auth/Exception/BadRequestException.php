<?php

declare(strict_types=1);

namespace Twitter\IAM\Domain\Auth\Exception;

use RuntimeException;

final class BadRequestException extends RuntimeException
{
    public const ERROR_CODE = 'BAD_REQUEST';

    public function __construct(string $message = 'Bad request.')
    {
        parent::__construct($message);
    }
}
