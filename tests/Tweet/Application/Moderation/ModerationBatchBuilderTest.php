<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Application\Moderation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\Moderation\ModerationBatch;
use Twitter\Tweet\Application\Moderation\ModerationBatchBuilder;
use Twitter\Tweet\Application\Moderation\ModerationBatchItem;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationMode;

#[Group('unit')]
#[CoversClass(ModerationBatchBuilder::class)]
final class ModerationBatchBuilderTest extends TestCase
{
    #[Test]
    public function returnsNullForEmptyInput(): void
    {
        $builder = new ModerationBatchBuilder($this->makeConfig());

        self::assertNull($builder->build([]));
    }

    #[Test]
    public function buildsSimpleBatchFromSingleItem(): void
    {
        // Arrange
        $builder = new ModerationBatchBuilder($this->makeConfig(maxItems: 10, softCap: 100, hardCap: 200));
        $item = new ModerationBatchItem('tweet-1', 'Hello world', 5, 0);

        // Act
        $batch = $builder->build([$item]);

        // Assert
        self::assertInstanceOf(ModerationBatch::class, $batch);
        self::assertSame(1, $batch->count());
        self::assertSame(5, $batch->estimatedTokens);
    }

    #[Test]
    public function respectsMaxItemsLimit(): void
    {
        // Arrange – 3 items but maxItems=2
        $builder = new ModerationBatchBuilder($this->makeConfig(maxItems: 2, softCap: 1000, hardCap: 2000));
        $items = [
            new ModerationBatchItem('t1', 'A', 10, 0),
            new ModerationBatchItem('t2', 'B', 10, 0),
            new ModerationBatchItem('t3', 'C', 10, 0),
        ];

        // Act
        $batch = $builder->build($items);

        // Assert
        self::assertNotNull($batch);
        self::assertSame(2, $batch->count());
        self::assertSame(['t1', 't2'], $batch->tweetIds());
    }

    #[Test]
    public function stopsBatchAtSoftTokenCapAfterFirstItem(): void
    {
        // Arrange – softCap=60; item1=50 tokens (added), item2=20 tokens (50+20=70 > 60 → stop)
        $builder = new ModerationBatchBuilder($this->makeConfig(maxItems: 10, softCap: 60, hardCap: 200));
        $items = [
            new ModerationBatchItem('t1', 'A', 50, 0),
            new ModerationBatchItem('t2', 'B', 20, 0),
        ];

        // Act
        $batch = $builder->build($items);

        // Assert – only first item included
        self::assertNotNull($batch);
        self::assertSame(1, $batch->count());
        self::assertSame(50, $batch->estimatedTokens);
    }

    #[Test]
    public function forcesFirstItemThroughEvenWhenItAloneExceedsHardTokenCap(): void
    {
        // Arrange – hardCap=50, single item has 80 tokens → forced through
        $builder = new ModerationBatchBuilder($this->makeConfig(maxItems: 10, softCap: 50, hardCap: 50));
        $item = new ModerationBatchItem('t1', 'A very long tweet text here', 80, 0);

        // Act
        $batch = $builder->build([$item]);

        // Assert – item is forced through despite exceeding the hard cap
        self::assertNotNull($batch);
        self::assertSame(1, $batch->count());
        self::assertSame(80, $batch->estimatedTokens);
    }

    #[Test]
    public function stopsAddingItemsWhenNextItemWouldExceedHardTokenCap(): void
    {
        // Arrange – hardCap=100; item1=60 tokens (added), item2=60 tokens (60+60=120 > 100 → stop)
        $builder = new ModerationBatchBuilder($this->makeConfig(maxItems: 10, softCap: 50, hardCap: 100));
        $items = [
            new ModerationBatchItem('t1', 'A', 60, 0),
            new ModerationBatchItem('t2', 'B', 60, 0),
        ];

        // Act
        $batch = $builder->build($items);

        // Assert – only first item is included
        self::assertNotNull($batch);
        self::assertSame(1, $batch->count());
        self::assertSame(['t1'], $batch->tweetIds());
    }

    #[Test]
    public function accumulatesTokensAcrossMultipleItemsBelowBothCaps(): void
    {
        // Arrange – softCap=100, hardCap=200; three items at 20 tokens each = 60 total (under both caps)
        $builder = new ModerationBatchBuilder($this->makeConfig(maxItems: 10, softCap: 100, hardCap: 200));
        $items = [
            new ModerationBatchItem('t1', 'A', 20, 0),
            new ModerationBatchItem('t2', 'B', 20, 0),
            new ModerationBatchItem('t3', 'C', 20, 0),
        ];

        // Act
        $batch = $builder->build($items);

        // Assert
        self::assertNotNull($batch);
        self::assertSame(3, $batch->count());
        self::assertSame(60, $batch->estimatedTokens);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function makeConfig(int $maxItems = 10, int $softCap = 100, int $hardCap = 200): ModerationConfig
    {
        return new ModerationConfig(
            enabled: true,
            mode: ModerationMode::LIVE,
            provider: 'openai',
            model: 'omni-moderation-latest',
            apiKey: 'sk-test',
            batchMaxItems: $maxItems,
            batchSoftTokenCap: $softCap,
            batchHardTokenCap: $hardCap,
            batchFlushIntervalMs: 500,
            limitRpm: 60,
            limitTpm: 100_000,
            limitRpd: 1_000,
            demoApprovePercent: 80,
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
