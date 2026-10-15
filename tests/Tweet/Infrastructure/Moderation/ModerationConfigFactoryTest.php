<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Infrastructure\Moderation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\Moderation\ModerationMode;
use Twitter\Tweet\Infrastructure\Moderation\ModerationConfigFactory;

#[Group('unit')]
#[CoversClass(ModerationConfigFactory::class)]
final class ModerationConfigFactoryTest extends TestCase
{
    #[Test]
    public function createBuildsValidConfig(): void
    {
        // Arrange
        $factory = $this->makeFactory();

        // Act
        $config = $factory->create();

        // Assert
        self::assertTrue($config->enabled);
        self::assertSame(ModerationMode::LIVE, $config->mode);
        self::assertSame('openai', $config->provider);
        self::assertSame('sk-test-key', $config->apiKey);
    }

    #[Test]
    public function createNormalizesEmptyStringApiKeyToNull(): void
    {
        $config = $this->makeFactory(apiKey: '')->create();

        self::assertNull($config->apiKey);
        self::assertFalse($config->hasApiKey());
    }

    #[Test]
    public function createNormalizesWhitespaceOnlyApiKeyToNull(): void
    {
        $config = $this->makeFactory(apiKey: '   ')->create();

        self::assertNull($config->apiKey);
        self::assertFalse($config->hasApiKey());
    }

    #[Test]
    public function createConvertsStringModeToEnum(): void
    {
        self::assertSame(ModerationMode::DEMO, $this->makeFactory(mode: 'demo')->create()->mode);
        self::assertSame(ModerationMode::BYPASS, $this->makeFactory(mode: 'bypass')->create()->mode);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeFactory(
        string $mode = 'live',
        ?string $apiKey = 'sk-test-key',
    ): ModerationConfigFactory {
        return new ModerationConfigFactory(
            enabled: true,
            mode: $mode,
            provider: 'openai',
            model: 'omni-moderation-latest',
            apiKey: $apiKey,
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
