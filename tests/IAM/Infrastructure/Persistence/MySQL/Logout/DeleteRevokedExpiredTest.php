<?php

declare(strict_types=1);

namespace Twitter\Tests\IAM\Infrastructure\Persistence\MySQL\Logout;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\IAM\Infrastructure\Persistence\MySQL\Repository\MySQLRefreshTokenRepository;

#[Group('unit')]
#[CoversClass(MySQLRefreshTokenRepository::class)]
final class DeleteRevokedExpiredTest extends TestCase
{
    private Connection&MockObject $connection;
    private MySQLRefreshTokenRepository $store;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->store = new MySQLRefreshTokenRepository($this->connection);
    }

    #[Test]
    public function itDeletesExpiredTokens(): void
    {
        $now = new DateTimeImmutable('2025-12-26 12:00:00');

        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('DELETE FROM refresh_tokens'),
                ['now' => $now->format('Y-m-d H:i:s')],
                ['now' => ParameterType::STRING],
            )
            ->willReturn(3);

        $deleted = $this->store->deleteRevokedExpired($now);

        self::assertSame(3, $deleted);
    }
}
