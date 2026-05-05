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
use ReflectionProperty;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationMode;
use Twitter\Tweet\Infrastructure\Moderation\RedisModerationWorkerLock;

#[Group('unit')]
#[CoversClass(RedisModerationWorkerLock::class)]
final class RedisModerationWorkerLockTest extends TestCase
{
    private Redis&MockObject $redis;
    private ModerationConfig $config;
    private RedisModerationWorkerLock $lock;

    protected function setUp(): void
    {
        $this->redis = $this->createMock(Redis::class);
        $this->config = $this->makeConfig();
        $this->lock = new RedisModerationWorkerLock($this->redis, $this->config);
    }

    // ── acquire ───────────────────────────────────────────────────────────────

    #[Test]
    public function acquireReturnsTrueWhenRedisSetSucceeds(): void
    {
        $this->redis
            ->expects(self::once())
            ->method('set')
            ->willReturn('OK');

        self::assertTrue($this->lock->acquire());
    }

    #[Test]
    public function acquireReturnsFalseWhenLockIsAlreadyHeld(): void
    {
        $this->redis
            ->expects(self::once())
            ->method('set')
            ->willReturn(false);

        self::assertFalse($this->lock->acquire());
    }

    #[Test]
    public function acquirePassesNxAndExOptionsToRedis(): void
    {
        $this->redis
            ->expects(self::once())
            ->method('set')
            ->with(
                $this->config->redisFlushLockKey,
                self::isString(),
                self::callback(
                    static fn (array $opts): bool => in_array('nx', $opts, true)
                        && isset($opts['ex'])
                        && 60 === $opts['ex'],
                ),
            )
            ->willReturn('OK');

        $this->lock->acquire();
    }

    // ── refresh ───────────────────────────────────────────────────────────────

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function refreshReturnsTrueWhenTokenMatchesAndExpireSucceeds(): void
    {
        // Arrange – read the randomly-generated token via reflection
        $token = (new ReflectionProperty(RedisModerationWorkerLock::class, 'token'))
            ->getValue($this->lock);

        $this->redis->method('get')->willReturn($token);
        $this->redis->method('expire')->willReturn(true);

        // Act & Assert
        self::assertTrue($this->lock->refresh());
    }

    #[Test]
    public function refreshReturnsFalseWhenKeyHasExpired(): void
    {
        // Redis returns false (key gone)
        $this->redis->method('get')->willReturn(false);
        $this->redis->expects(self::never())->method('expire');

        self::assertFalse($this->lock->refresh());
    }

    #[Test]
    public function refreshReturnsFalseWhenTokenDoesNotMatchStoredValue(): void
    {
        $this->redis->method('get')->willReturn('completely-different-token');
        $this->redis->expects(self::never())->method('expire');

        self::assertFalse($this->lock->refresh());
    }

    #[Test]
    public function refreshPassesCorrectTtlToExpire(): void
    {
        $token = (new ReflectionProperty(RedisModerationWorkerLock::class, 'token'))
            ->getValue($this->lock);

        $this->redis->method('get')->willReturn($token);

        $this->redis
            ->expects(self::once())
            ->method('expire')
            ->with($this->config->redisFlushLockKey, $this->config->workerLockTtlSeconds)
            ->willReturn(true);

        $this->lock->refresh();
    }

    // ── release ───────────────────────────────────────────────────────────────

    #[Test]
    public function releaseDeletesKeyWhenTokenMatches(): void
    {
        $token = (new ReflectionProperty(RedisModerationWorkerLock::class, 'token'))
            ->getValue($this->lock);

        $this->redis->method('get')->willReturn($token);

        $this->redis
            ->expects(self::once())
            ->method('del')
            ->with($this->config->redisFlushLockKey);

        $this->lock->release();
    }

    #[Test]
    public function releaseDoesNothingWhenTokenDoesNotMatch(): void
    {
        $this->redis->method('get')->willReturn('wrong-token');
        $this->redis->expects(self::never())->method('del');

        $this->lock->release();
    }

    #[Test]
    public function releaseDoesNothingWhenKeyHasAlreadyExpired(): void
    {
        $this->redis->method('get')->willReturn(false);
        $this->redis->expects(self::never())->method('del');

        $this->lock->release();
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
