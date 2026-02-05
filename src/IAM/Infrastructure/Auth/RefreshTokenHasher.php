<?php

declare(strict_types=1);

namespace Twitter\IAM\Infrastructure\Auth;

final readonly class RefreshTokenHasher
{
    public function __construct(private string $secret) {}

    public function hash(string $refreshToken): string
    {
        return hash_hmac('sha256', $refreshToken, $this->secret, true);
    }
}
