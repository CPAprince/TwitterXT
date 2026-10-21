<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Infrastructure\Moderation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\Moderation\ModerationDecision;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Infrastructure\Moderation\MySqlModerationStatusUpdater;

#[Group('unit')]
#[CoversClass(MySqlModerationStatusUpdater::class)]
final class MySqlModerationStatusUpdaterTest extends TestCase
{
    private Connection&MockObject $connection;
    private MySqlModerationStatusUpdater $updater;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->updater = new MySqlModerationStatusUpdater($this->connection);
    }

    // ── apply: empty ──────────────────────────────────────────────────────────

    #[Test]
    public function applyDoesNothingForEmptyDecisions(): void
    {
        $this->connection->expects(self::never())->method('executeStatement');

        $this->updater->apply([]);
    }

    // ── apply: single approved decision ───────────────────────────────────────

    #[Test]
    public function applyExecutesUpdateWithApprovedStatus(): void
    {
        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $version = 2;

        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('moderation_status = ?'),
                self::callback(function (array $params) use ($version): bool {
                    return Tweet::MODERATION_APPROVED === $params[0]
                        && $params[3] === $version
                        && Tweet::MODERATION_PENDING === $params[4];
                }),
            );

        $this->updater->apply([new ModerationDecision($tweetId, true, $version)]);
    }

    // ── apply: single rejected decision ───────────────────────────────────────

    #[Test]
    public function applyExecutesUpdateWithRejectedStatus(): void
    {
        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $version = 1;

        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('moderation_status = ?'),
                self::callback(function (array $params) use ($version): bool {
                    return Tweet::MODERATION_REJECTED === $params[0]
                        && $params[3] === $version
                        && Tweet::MODERATION_PENDING === $params[4];
                }),
            );

        $this->updater->apply([new ModerationDecision($tweetId, false, $version)]);
    }

    // ── apply: multiple decisions ─────────────────────────────────────────────

    #[Test]
    public function applyCallsExecuteStatementOncePerDecision(): void
    {
        $this->connection
            ->expects(self::exactly(3))
            ->method('executeStatement');

        $this->updater->apply([
            new ModerationDecision('019b5f3f-d110-7908-9177-5df439942a8b', true, 1),
            new ModerationDecision('019b5f41-0e5b-7f65-8b7a-0f9c0b3b3c11', false, 1),
            new ModerationDecision('550e8400-e29b-41d4-a716-446655440000', true, 2),
        ]);
    }

    // ── moderation_version guard ──────────────────────────────────────────────

    #[Test]
    public function sqlIncludesModerationVersionGuard(): void
    {
        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('AND moderation_version = ?'),
            );

        $this->updater->apply([new ModerationDecision('019b5f3f-d110-7908-9177-5df439942a8b', true, 3)]);
    }

    #[Test]
    public function sqlIncludesPendingStatusGuard(): void
    {
        // UPDATE must only touch rows where moderation_status = PENDING,
        // preventing double-moderation of already-decided tweets.
        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('AND moderation_status = ?'),
            );

        $this->updater->apply([new ModerationDecision('019b5f3f-d110-7908-9177-5df439942a8b', true, 1)]);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function passesCorrectModerationVersionToStatement(): void
    {
        $version = 5;

        $captured = [];
        $this->connection
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$captured): int {
                $captured = $params;

                return 1;
            });

        $this->updater->apply([new ModerationDecision('019b5f3f-d110-7908-9177-5df439942a8b', true, $version)]);

        // Params order: [targetStatus, reason, binaryId, moderationVersion, pendingStatus]
        self::assertSame($version, $captured[3]);
    }

    #[Test]
    public function applyPassesModerationReasonToStatement(): void
    {
        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $reason = 'Flagged: hate';

        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('moderation_reason = ?'),
                self::callback(function (array $params) use ($reason): bool {
                    return $params[1] === $reason;
                }),
            );

        $this->updater->apply([new ModerationDecision($tweetId, false, 1, reason: $reason)]);
    }

    #[Test]
    public function markFailedUpdatesStatusAndReason(): void
    {
        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $this->connection
            ->expects(self::once())
            ->method('executeStatement')
            ->with(
                self::stringContains('moderation_status = ?'),
                self::callback(function (array $params): bool {
                    return Tweet::MODERATION_FAILED === $params[0]
                        && 'Failed after multiple moderation attempts.' === $params[1]
                        && Tweet::MODERATION_PENDING === $params[3];
                }),
            );

        $this->updater->markFailed([$tweetId]);
    }

    #[Test]
    public function applyReturnsOnlySuccessfullyUpdatedDecisions(): void
    {
        $d1 = new ModerationDecision('019b5f3f-d110-7908-9177-5df439942a8b', true, 1, userId: 'author-1');
        $d2 = new ModerationDecision('019b5f41-0e5b-7f65-8b7a-0f9c0b3b3c11', true, 1, userId: 'author-2'); // version conflict -> returns 0
        $d3 = new ModerationDecision('550e8400-e29b-41d4-a716-446655440000', false, 2, userId: 'author-3');

        $this->connection
            ->expects(self::exactly(3))
            ->method('executeStatement')
            ->willReturnOnConsecutiveCalls(1, 0, 1);

        $applied = $this->updater->apply([$d1, $d2, $d3]);

        self::assertSame([$d1, $d3], $applied);
    }
}
