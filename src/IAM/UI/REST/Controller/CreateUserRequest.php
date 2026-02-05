<?php

declare(strict_types=1);

namespace Twitter\IAM\UI\REST\Controller;

use Assert\Assert;

final readonly class CreateUserRequest
{
    public function __construct(
        private string $email,
        private string $password,
    ) {
        Assert::lazy()
            ->tryAll()
            ->that($this->email, 'email')
            ->email()
            ->that($this->password, 'password')
            ->notBlank()
            ->minLength(8)
            ->regex('/[a-z]/', 'Password must contain at least one lowercase letter')
            ->regex('/[A-Z]/', 'Password must contain at least one uppercase letter')
            ->regex('/\d/', 'Password must contain at least one digit')
            ->regex('/[^a-zA-Z0-9]/', 'Password must contain at least one special character')
            ->verifyNow();
    }

    public function email(): string
    {
        return $this->email;
    }

    public function password(): string
    {
        return $this->password;
    }
}
