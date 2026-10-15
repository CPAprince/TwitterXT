<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Infrastructure\Moderation;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Redis;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationMode;
use Twitter\Tweet\Infrastructure\Moderation\RedisModerationQueue;

#[Group('unit')]
#[CoversClass(RedisModerationQueue::class)]
final class RedisModerationQueueTest extends TestCase
{
    private Redis&MockObject $redis;
    private RedisModerationQueue $queue;
    private ModerationConfig $config;

    protected function setUp(): void
    {
        $this->redis = $this->createMock(Redis::class);
        $this->config = $this->makeConfig();
        $this->queue = new RedisModerationQueue($this->redis, $this->config);
    }

    #[Test]
    public function enqueueCallsRPushWithCorrectKeyAndId(): void
    {
        $this->redis
            ->expects(self::once())
            ->method('rPush')
            ->with($this->config->redisQueueKey, 'tweet-123');

        $this->queue->enqueue('tweet-123');
    }

    #[Test]
    public function peekReturnsItemsFromRedisListHead(): void
    {
        // Arrange
        $this->redis
            ->expects(self::once())
            ->method('lRange')
            ->with($this->config->redisQueueKey, 0, 2)   // limit 3 → indices 0..2
            ->willReturn(['tweet-1', 'tweet-2', 'tweet-3']);

        // Act
        $result = $this->queue->peek(3);

        // Assert
        self::assertSame(['tweet-1', 'tweet-2', 'tweet-3'], $result);
    }

    #[Test]
    public function peekReturnsEmptyArrayWhenLimitIsZero(): void
    {
        $this->redis->expects(self::never())->method('lRange');

        self::assertSame([], $this->queue->peek(0));
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function peekFiltersBlankValuesReturnedByRedis(): void
    {
        $this->redis
            ->method('lRange')
            ->willReturn(['tweet-1', '  ', '', 'tweet-2']);

        $result = $this->queue->peek(10);

        self::assertSame(['tweet-1', 'tweet-2'], $result);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function peekReturnsEmptyArrayWhenRedisReturnsNonArray(): void
    {
        $this->redis->method('lRange')->willReturn(false);

        self::assertSame([], $this->queue->peek(5));
    }

    #[Test]
    public function removeCallsLRemOncePerTweetId(): void
    {
        // Arrange
        $tweetIds = ['tweet-1', 'tweet-2', 'tweet-3'];

        $this->redis
            ->expects(self::exactly(3))
            ->method('lRem')
            ->with($this->config->redisQueueKey, self::anything(), 1);

        // Act
        $this->queue->remove($tweetIds);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function removeCallsLRemWithCorrectTweetId(): void
    {
        // Capture the actual value passed to lRem
        $removed = [];
        $this->redis
            ->method('lRem')
            ->willReturnCallback(function (string $key, string $value, int $count) use (&$removed): int {
                $removed[] = $value;

                return 1;
            });

        $this->queue->remove(['tweet-abc', 'tweet-xyz']);

        self::assertSame(['tweet-abc', 'tweet-xyz'], $removed);
    }

    #[Test]
    public function removeDoesNothingForEmptyArray(): void
    {
        $this->redis->expects(self::never())->method('lRem');

        $this->queue->remove([]);
    }

    #[Test]
    public function sizeCallsLLenWithQueueKey(): void
    {
        $this->redis
            ->expects(self::once())
            ->method('lLen')
            ->with($this->config->redisQueueKey)
            ->willReturn(7);

        self::assertSame(7, $this->queue->size());
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeConfig(): ModerationConfig
    {
        return new ModerationConfig(
            enabled: true,
            mode: ModerationMode::LIVE,
            provider: 'openai',
            model: 'omni-moderation-latest',
            apiKey: 'sk-test',
            batchMaxItems: 10,
            batchSoftTokenCap: 1_000,
            batchHardTokenCap: 2_000,
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
