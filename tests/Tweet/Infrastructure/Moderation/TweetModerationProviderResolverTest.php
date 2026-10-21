<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Infrastructure\Moderation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationMode;
use Twitter\Tweet\Infrastructure\Moderation\BypassTweetModerationProvider;
use Twitter\Tweet\Infrastructure\Moderation\DemoTweetModerationProvider;
use Twitter\Tweet\Infrastructure\Moderation\OpenAiTweetModerationProvider;
use Twitter\Tweet\Infrastructure\Moderation\TweetModerationProviderResolver;

#[Group('unit')]
#[CoversClass(TweetModerationProviderResolver::class)]
final class TweetModerationProviderResolverTest extends TestCase
{
    #[Test]
    public function resolvesOpenAiProviderWhenLiveMode(): void
    {
        // Arrange
        $config = $this->makeConfig(mode: ModerationMode::LIVE);
        $resolver = $this->makeResolver($config);

        // Act & Assert
        self::assertInstanceOf(OpenAiTweetModerationProvider::class, $resolver->resolve());
    }

    #[Test]
    public function resolvesDemoProviderWhenDemoMode(): void
    {
        // Arrange
        $config = $this->makeConfig(mode: ModerationMode::DEMO);
        $resolver = $this->makeResolver($config);

        // Act & Assert
        self::assertInstanceOf(DemoTweetModerationProvider::class, $resolver->resolve());
    }

    #[Test]
    public function resolvesBypassProviderWhenBypassMode(): void
    {
        // Arrange
        $config = $this->makeConfig(mode: ModerationMode::BYPASS);
        $resolver = $this->makeResolver($config);

        // Act & Assert
        self::assertInstanceOf(BypassTweetModerationProvider::class, $resolver->resolve());
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeResolver(ModerationConfig $config): TweetModerationProviderResolver
    {
        $bypassProvider = new BypassTweetModerationProvider();
        $demoProvider = new DemoTweetModerationProvider($config);
        $openAiProvider = new OpenAiTweetModerationProvider(
            $this->makeConfig(mode: ModerationMode::LIVE),
        );

        return new TweetModerationProviderResolver($config, $demoProvider, $bypassProvider, $openAiProvider);
    }

    private function makeConfig(ModerationMode $mode): ModerationConfig
    {
        return new ModerationConfig(
            mode: $mode,
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
