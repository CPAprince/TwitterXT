<?php

declare(strict_types=1);

namespace Twitter\IAM\Application\Logout;

use DateTimeImmutable;

interface RefreshTokenRepository
{
    public function revoke(string $refreshToken, string $username): void;

    public function deleteRevokedExpired(DateTimeImmutable $now): int;
}
