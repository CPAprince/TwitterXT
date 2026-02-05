<?php

declare(strict_types=1);

namespace Twitter\IAM\Application\CreateUser;

final readonly class CreateUserCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
