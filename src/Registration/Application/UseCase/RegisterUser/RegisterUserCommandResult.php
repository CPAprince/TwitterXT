<?php

declare(strict_types=1);

namespace Twitter\Registration\Application\UseCase\RegisterUser;

final readonly class RegisterUserCommandResult
{
    public function __construct(
        public string $userId,
    ) {}
}
