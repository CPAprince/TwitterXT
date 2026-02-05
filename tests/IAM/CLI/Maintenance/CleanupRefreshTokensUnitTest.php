<?php

declare(strict_types=1);

namespace Twitter\Tests\IAM\UI\CLI\Maintenance;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Twitter\IAM\Application\Logout\RefreshTokenRepository;
use Twitter\IAM\UI\CLI\Maintenance\CleanupRefreshTokensCommand;

#[Group('unit')]
#[CoversClass(CleanupRefreshTokensCommand::class)]
final class CleanupRefreshTokensUnitTest extends TestCase
{
    #[Test]
    public function itOutputsDeletedCount(): void
    {
        $repo = $this->createMock(RefreshTokenRepository::class);

        $repo
            ->expects(self::once())
            ->method('deleteRevokedExpired')
            ->with(self::isInstanceOf(DateTimeImmutable::class))
            ->willReturn(5);

        $command = new CleanupRefreshTokensCommand($repo);
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Deleted 5', $tester->getDisplay());
    }
}
