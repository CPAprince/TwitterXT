<?php

declare(strict_types=1);

namespace Twitter\IAM\Infrastructure\Persistence\MySQL\Repository;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Twitter\IAM\Application\Logout\RefreshTokenRepository;
use Twitter\IAM\Domain\Auth\Exception\TokenInvalidException;

final class MySQLRefreshTokenRepository implements RefreshTokenRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {}

    public function revoke(string $refreshToken, string $username): void
    {
        $now = new DateTimeImmutable();

        $affected = $this->connection->executeStatement(
            <<<SQL
                UPDATE refresh_tokens
                SET valid = :now
                WHERE refresh_token = :token
                  AND username = :username
                  AND valid > :now
                SQL,
            [
                'now' => $now->format('Y-m-d H:i:s'),
                'token' => $refreshToken,
                'username' => $username,
            ],
            [
                'now' => ParameterType::STRING,
                'token' => ParameterType::STRING,
                'username' => ParameterType::STRING,
            ],
        );

        if (0 === $affected) {
            throw new TokenInvalidException();
        }
    }

    public function deleteRevokedExpired(DateTimeImmutable $now): int
    {
        return $this->connection->executeStatement(
            <<<SQL
                DELETE FROM refresh_tokens
                WHERE valid <= :now
                SQL,
            ['now' => $now->format('Y-m-d H:i:s')],
            ['now' => ParameterType::STRING],
        );
    }
}
