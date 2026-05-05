<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Application\Moderation;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationMode;

#[Group('unit')]
#[CoversClass(ModerationConfig::class)]
final class ModerationConfigTest extends TestCase
{
    // ── valid config ──────────────────────────────────────────────────────────

    #[Test]
    public function createsValidConfig(): void
    {
        $config = $this->makeConfig();

        self::assertTrue($config->isEnabled());
        self::assertSame(ModerationMode::LIVE, $config->mode);
        self::assertSame('openai', $config->provider);
    }

    // ── assertValid: invalid cases ────────────────────────────────────────────

    #[Test]
    #[DataProvider('invalidConfigProvider')]
    public function throwsOnInvalidParam(array $override): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeConfig($override);
    }

    public static function invalidConfigProvider(): array
    {
        return [
            'empty provider' => [['provider' => '']],
            'empty model' => [['model' => '']],
            'batchMaxItems zero' => [['batchMaxItems' => 0]],
            'batchMaxItems negative' => [['batchMaxItems' => -1]],
            'batchSoftTokenCap zero' => [['batchSoftTokenCap' => 0]],
            'batchHardTokenCap zero' => [['batchHardTokenCap' => 0]],
            'softTokenCap exceeds hardTokenCap' => [['batchSoftTokenCap' => 2001, 'batchHardTokenCap' => 2000]],
            'batchFlushIntervalMs zero' => [['batchFlushIntervalMs' => 0]],
            'limitRpm zero' => [['limitRpm' => 0]],
            'limitTpm zero' => [['limitTpm' => 0]],
            'limitRpd zero' => [['limitRpd' => 0]],
            'demoApprovePercent negative' => [['demoApprovePercent' => -1]],
            'demoApprovePercent above 100' => [['demoApprovePercent' => 101]],
            'redisQueueKey empty' => [['redisQueueKey' => '']],
            'redisFlushLockKey empty' => [['redisFlushLockKey' => '']],
            'workerIdleSleepMs zero' => [['workerIdleSleepMs' => 0]],
            'workerLockTtlSeconds zero' => [['workerLockTtlSeconds' => 0]],
            'workerMaxFetchItems zero' => [['workerMaxFetchItems' => 0]],
        ];
    }

    // ── mode helpers ──────────────────────────────────────────────────────────

    #[Test]
    public function requiresLiveApiCallWhenEnabledAndLiveMode(): void
    {
        $config = $this->makeConfig(['enabled' => true, 'mode' => ModerationMode::LIVE]);

        self::assertTrue($config->requiresLiveApiCall());
        self::assertFalse($config->usesDemoMode());
        self::assertFalse($config->usesBypassMode());
    }

    #[Test]
    public function usesDemoModeWhenEnabledAndDemoMode(): void
    {
        $config = $this->makeConfig(['enabled' => true, 'mode' => ModerationMode::DEMO]);

        self::assertFalse($config->requiresLiveApiCall());
        self::assertTrue($config->usesDemoMode());
        self::assertFalse($config->usesBypassMode());
    }

    #[Test]
    public function usesBypassModeWhenEnabledAndBypassMode(): void
    {
        $config = $this->makeConfig(['enabled' => true, 'mode' => ModerationMode::BYPASS]);

        self::assertFalse($config->requiresLiveApiCall());
        self::assertFalse($config->usesDemoMode());
        self::assertTrue($config->usesBypassMode());
    }

    #[Test]
    public function alwaysUsesBypassModeWhenDisabledRegardlessOfMode(): void
    {
        foreach ([ModerationMode::LIVE, ModerationMode::DEMO, ModerationMode::BYPASS] as $mode) {
            $config = $this->makeConfig(['enabled' => false, 'mode' => $mode]);

            self::assertTrue($config->usesBypassMode(), "Expected bypass for mode {$mode->value} when disabled");
            self::assertFalse($config->requiresLiveApiCall());
            self::assertFalse($config->usesDemoMode());
        }
    }

    // ── hasApiKey ─────────────────────────────────────────────────────────────

    #[Test]
    public function hasApiKeyReturnsFalseWhenNull(): void
    {
        $config = $this->makeConfig(['apiKey' => null]);

        self::assertFalse($config->hasApiKey());
    }

    #[Test]
    public function hasApiKeyReturnsFalseForWhitespaceOnlyString(): void
    {
        $config = $this->makeConfig(['apiKey' => '   ']);

        self::assertFalse($config->hasApiKey());
    }

    #[Test]
    public function hasApiKeyReturnsTrueWhenSet(): void
    {
        $config = $this->makeConfig(['apiKey' => 'sk-test-key']);

        self::assertTrue($config->hasApiKey());
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeConfig(array $override = []): ModerationConfig
    {
        $defaults = [
            'enabled' => true,
            'mode' => ModerationMode::LIVE,
            'provider' => 'openai',
            'model' => 'omni-moderation-latest',
            'apiKey' => 'sk-test',
            'batchMaxItems' => 10,
            'batchSoftTokenCap' => 1_000,
            'batchHardTokenCap' => 2_000,
            'batchFlushIntervalMs' => 500,
            'limitRpm' => 60,
            'limitTpm' => 100_000,
            'limitRpd' => 1_000,
            'demoApprovePercent' => 80,
            'redisQueueKey' => 'moderation:queue',
            'redisFlushLockKey' => 'moderation:lock',
            'redisRateReqPrefix' => 'moderation:rate:req:',
            'redisRateTokPrefix' => 'moderation:rate:tok:',
            'redisRateReqDayPrefix' => 'moderation:rate:day:',
            'workerIdleSleepMs' => 1_000,
            'workerLockTtlSeconds' => 60,
            'workerMaxFetchItems' => 100,
        ];

        return new ModerationConfig(...array_merge($defaults, $override));
    }
}
