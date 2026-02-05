<?php

declare(strict_types=1);

namespace Twitter\Tests\IAM\Infrastructure\Persistence\MySQL\Logout;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\IAM\Domain\Auth\Exception\TokenInvalidException;
use Twitter\IAM\Infrastructure\Persistence\MySQL\Repository\MySQLRefreshTokenRepository;

#[Group('unit')]
#[CoversClass(MySQLRefreshTokenRepository::class)]
final class DbalRefreshTokenTest extends TestCase
{
    private Connection&MockObject $connection;
    private MySQLRefreshTokenRepository $store;

    private const string USERNAME = 'fresh@gmail.com';
    private const string REFRESH_TOKEN = 'rt_example_123';

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->store = new MySQLRefreshTokenRepository($this->connection);
    }

    #[Test]
    public function itRevokesTokenWhenOneRowWasUpdated(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->willReturn(1);

        $this->store->revoke(self::REFRESH_TOKEN, self::USERNAME);
    }

    #[Test]
    public function itThrowsWhenNoRowsWereUpdated(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->willReturn(0);

        $this->expectException(TokenInvalidException::class);

        $this->store->revoke(self::REFRESH_TOKEN, self::USERNAME);
    }
}
