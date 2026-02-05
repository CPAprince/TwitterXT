<?php

declare(strict_types=1);

namespace Twitter\Registration\Application\UseCase\RegisterUser;

final readonly class RegisterUserCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public string $name,
        public ?string $bio = null,
    ) {}
}
