<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Application\Moderation;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationMode;
use Twitter\Tweet\Application\Moderation\TweetModerationPolicy;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;

#[Group('unit')]
#[CoversClass(TweetModerationPolicy::class)]
final class TweetModerationPolicyTest extends TestCase
{
    #[Test]
    public function onContentWrittenApprovesSynchronouslyInBypassMode(): void
    {
        $config = $this->createConfig(ModerationMode::BYPASS);
        $policy = new TweetModerationPolicy($config);

        $tweet = Tweet::create('019b5f3f-d110-7908-9177-5df439942a8b', 'Bypass test tweet');

        $policy->onContentWritten($tweet);

        self::assertSame(Tweet::MODERATION_APPROVED, $tweet->moderationStatus());
        self::assertInstanceOf(DateTimeImmutable::class, $tweet->moderatedAt());
    }

    #[Test]
    public function onContentWrittenKeepsPendingInDemoMode(): void
    {
        $config = $this->createConfig(ModerationMode::DEMO);
        $policy = new TweetModerationPolicy($config);

        $tweet = Tweet::create('019b5f3f-d110-7908-9177-5df439942a8b', 'Demo test tweet');

        $policy->onContentWritten($tweet);

        self::assertSame(Tweet::MODERATION_PENDING, $tweet->moderationStatus());
        self::assertNull($tweet->moderatedAt());
    }

    #[Test]
    public function onContentWrittenKeepsPendingInLiveMode(): void
    {
        $config = $this->createConfig(ModerationMode::LIVE);
        $policy = new TweetModerationPolicy($config);

        $tweet = Tweet::create('019b5f3f-d110-7908-9177-5df439942a8b', 'Live test tweet');

        $policy->onContentWritten($tweet);

        self::assertSame(Tweet::MODERATION_PENDING, $tweet->moderationStatus());
        self::assertNull($tweet->moderatedAt());
    }

    private function createConfig(ModerationMode $mode): ModerationConfig
    {
        return new ModerationConfig(
            mode: $mode,
            provider: 'openai',
            model: 'omni-moderation-latest',
            apiKey: 'sk-test',
            batchMaxItems: 25,
            batchSoftTokenCap: 1800,
            batchHardTokenCap: 2000,
            batchFlushIntervalMs: 300,
            limitRpm: 490,
            limitTpm: 9000,
            limitRpd: 9800,
            demoApprovePercent: 95,
            redisRateReqPrefix: 'moderation:rate:req:',
            redisRateTokPrefix: 'moderation:rate:tok:',
            redisRateReqDayPrefix: 'moderation:rate:reqday:',
            workerIdleSleepMs: 1000,
            workerLockTtlSeconds: 30,
            workerMaxFetchItems: 100,
        );
    }
}
