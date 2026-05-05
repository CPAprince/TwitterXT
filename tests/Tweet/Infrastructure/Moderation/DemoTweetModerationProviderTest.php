<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Infrastructure\Moderation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\Moderation\ModerationBatchItem;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationMode;
use Twitter\Tweet\Infrastructure\Moderation\DemoTweetModerationProvider;

#[Group('unit')]
#[CoversClass(DemoTweetModerationProvider::class)]
final class DemoTweetModerationProviderTest extends TestCase
{
    #[Test]
    public function alwaysApprovesWhenApprovePercentIs100(): void
    {
        // Arrange
        $provider = new DemoTweetModerationProvider($this->makeConfig(approvePercent: 100));
        $items = array_map(
            static fn (int $i) => new ModerationBatchItem("tweet-$i", "Text $i", 3, 0),
            range(1, 20),
        );

        // Act
        $decisions = $provider->moderateBatch($items);

        // Assert – every decision must be approved
        foreach ($decisions as $decision) {
            self::assertTrue($decision->approved);
        }
    }

    #[Test]
    public function alwaysRejectsWhenApprovePercentIsZero(): void
    {
        // Arrange
        $provider = new DemoTweetModerationProvider($this->makeConfig(approvePercent: 0));
        $items = array_map(
            static fn (int $i) => new ModerationBatchItem("tweet-$i", "Text $i", 3, 0),
            range(1, 20),
        );

        // Act
        $decisions = $provider->moderateBatch($items);

        // Assert – every decision must be rejected
        foreach ($decisions as $decision) {
            self::assertFalse($decision->approved);
            self::assertNotNull($decision->reason);
        }
    }

    #[Test]
    public function returnsOneDecisionPerInputItem(): void
    {
        // Arrange
        $provider = new DemoTweetModerationProvider($this->makeConfig(approvePercent: 80));
        $items = [
            new ModerationBatchItem('t1', 'A', 2, 0),
            new ModerationBatchItem('t2', 'B', 2, 0),
            new ModerationBatchItem('t3', 'C', 2, 0),
        ];

        // Act
        $decisions = $provider->moderateBatch($items);

        // Assert
        self::assertCount(3, $decisions);
        self::assertSame('t1', $decisions[0]->tweetId);
        self::assertSame('t2', $decisions[1]->tweetId);
        self::assertSame('t3', $decisions[2]->tweetId);
    }

    #[Test]
    public function returnsEmptyArrayForEmptyInput(): void
    {
        $provider = new DemoTweetModerationProvider($this->makeConfig(approvePercent: 80));
        $decisions = $provider->moderateBatch([]);

        self::assertSame([], $decisions);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeConfig(int $approvePercent): ModerationConfig
    {
        return new ModerationConfig(
            enabled: true,
            mode: ModerationMode::DEMO,
            provider: 'demo',
            model: 'demo',
            apiKey: null,
            batchMaxItems: 10,
            batchSoftTokenCap: 1_000,
            batchHardTokenCap: 2_000,
            batchFlushIntervalMs: 500,
            limitRpm: 60,
            limitTpm: 100_000,
            limitRpd: 1_000,
            demoApprovePercent: $approvePercent,
            redisQueueKey: 'moderation:queue',
            redisFlushLockKey: 'moderation:lock',
            redisRateReqPrefix: 'moderation:rate:req:',
            redisRateTokPrefix: 'moderation:rate:tok:',
            redisRateReqDayPrefix: 'moderation:rate:day:',
            workerIdleSleepMs: 1_000,
            workerLockTtlSeconds: 60,
            workerMaxFetchItems: 100,
        );
    }
}
