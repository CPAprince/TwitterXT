<?php

declare(strict_types=1);

namespace Twitter\IAM\Domain\User\Exception;

use Exception;

final class UserAlreadyExistsException extends Exception
{
    public function __construct(string $email)
    {
        parent::__construct(sprintf('User with email "%s" already exists', $email));
    }
}
