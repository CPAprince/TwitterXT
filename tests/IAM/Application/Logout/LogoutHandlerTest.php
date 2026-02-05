<?php

declare(strict_types=1);

namespace Twitter\Tests\IAM\Application\Logout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\IAM\Application\Logout\LogoutCommand;
use Twitter\IAM\Application\Logout\LogoutHandler;
use Twitter\IAM\Application\Logout\RefreshTokenRepository;

#[Group('unit')]
#[CoversClass(LogoutHandler::class)]
final class LogoutHandlerTest extends TestCase
{
    private RefreshTokenRepository&MockObject $refreshTokenStore;
    private LogoutHandler $handler;

    protected function setUp(): void
    {
        $this->refreshTokenStore = $this->createMock(RefreshTokenRepository::class);
        $this->handler = new LogoutHandler($this->refreshTokenStore);
    }

    #[Test]
    public function itRevokesRefreshTokenForUser(): void
    {
        $username = 'fresh@gmail.com';
        $refreshToken = 'some-refresh-token';

        $this->refreshTokenStore
            ->expects(self::once())
            ->method('revoke')
            ->with($refreshToken, $username);

        ($this->handler)(new LogoutCommand(
            username: $username,
            refreshToken: $refreshToken,
        ));
    }
}
