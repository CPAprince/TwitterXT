<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Infrastructure\Moderation;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Redis;
use RuntimeException;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationMode;
use Twitter\Tweet\Infrastructure\Moderation\RedisModerationRateLimiter;

#[Group('unit')]
#[CoversClass(RedisModerationRateLimiter::class)]
final class RedisModerationRateLimiterTest extends TestCase
{
    private Redis&MockObject $redis;
    private RedisModerationRateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->redis = $this->createMock(Redis::class);
        $this->rateLimiter = new RedisModerationRateLimiter($this->redis, $this->makeConfig());
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throwsWhenBatchTokensIsZero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rateLimiter->reserveCapacity(0);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throwsWhenBatchTokensIsNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->rateLimiter->reserveCapacity(-5);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function returnsAllowedResultWhenRedisLuaPermits(): void
    {
        // Arrange – Lua script returns [1, 'allowed']
        $this->redis
            ->method('eval')
            ->willReturn([1, 'allowed']);

        // Act
        $result = $this->rateLimiter->reserveCapacity(50);

        // Assert
        self::assertTrue($result->allowed);
        self::assertNull($result->reason);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function returnsDeniedWithReasonWhenRpmExceeded(): void
    {
        $this->redis
            ->method('eval')
            ->willReturn([0, 'rpm_exceeded']);

        $result = $this->rateLimiter->reserveCapacity(50);

        self::assertFalse($result->allowed);
        self::assertSame('rpm_exceeded', $result->reason);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function returnsDeniedWithReasonWhenTpmExceeded(): void
    {
        $this->redis
            ->method('eval')
            ->willReturn([0, 'tpm_exceeded']);

        $result = $this->rateLimiter->reserveCapacity(50);

        self::assertFalse($result->allowed);
        self::assertSame('tpm_exceeded', $result->reason);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function returnsDeniedWithReasonWhenRpdExceeded(): void
    {
        $this->redis
            ->method('eval')
            ->willReturn([0, 'rpd_exceeded']);

        $result = $this->rateLimiter->reserveCapacity(50);

        self::assertFalse($result->allowed);
        self::assertSame('rpd_exceeded', $result->reason);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throwsWhenRedisReturnsUnexpectedFormat(): void
    {
        $this->redis
            ->method('eval')
            ->willReturn('unexpected_string');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unexpected Redis rate limiter response/');

        $this->rateLimiter->reserveCapacity(50);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function throwsWhenRedisReturnsTooFewElements(): void
    {
        $this->redis
            ->method('eval')
            ->willReturn([1]);   // only one element instead of two

        $this->expectException(RuntimeException::class);

        $this->rateLimiter->reserveCapacity(50);
    }

    #[Test]
    public function evalIsCalledWithThreeKeyArgument(): void
    {
        // The third argument to eval must be 3 (number of KEYS)
        $this->redis
            ->expects(self::once())
            ->method('eval')
            ->with(
                self::isString(),  // Lua script
                self::isArray(),   // keys + argv
                3,                       // numkeys
            )
            ->willReturn([1, 'allowed']);

        $this->rateLimiter->reserveCapacity(100);
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
