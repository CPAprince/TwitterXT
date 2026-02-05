<?php

declare(strict_types=1);

namespace Twitter\IAM\Domain\User\Exception;

use Exception;

final class InvalidEmailException extends Exception
{
    public function __construct(string $email)
    {
        parent::__construct(sprintf('"%s" is not a valid email', $email));
    }
}
