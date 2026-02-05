<?php

declare(strict_types=1);

namespace Twitter\IAM\Application\Logout;

final readonly class LogoutCommand
{
    public function __construct(
        private string $username,
        private string $refreshToken,
    ) {}

    public function username(): string
    {
        return $this->username;
    }

    public function refreshToken(): string
    {
        return $this->refreshToken;
    }
}
