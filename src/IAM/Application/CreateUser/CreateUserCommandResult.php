<?php

declare(strict_types=1);

namespace Twitter\IAM\Application\CreateUser;

final readonly class CreateUserCommandResult
{
    public function __construct(
        public string $userId,
    ) {}
}
