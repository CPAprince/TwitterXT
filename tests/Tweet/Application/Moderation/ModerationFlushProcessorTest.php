<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Application\Moderation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Twitter\Tweet\Application\Moderation\ModeratedTweetsCacheInvalidatorInterface;
use Twitter\Tweet\Application\Moderation\ModerationBatchBuilder;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationDecision;
use Twitter\Tweet\Application\Moderation\ModerationFlushProcessor;
use Twitter\Tweet\Application\Moderation\ModerationMode;
use Twitter\Tweet\Application\Moderation\ModerationRateLimiterInterface;
use Twitter\Tweet\Application\Moderation\ModerationRateLimitResult;
use Twitter\Tweet\Application\Moderation\ModerationStatusUpdaterInterface;
use Twitter\Tweet\Application\Moderation\ModerationTweetCandidate;
use Twitter\Tweet\Application\Moderation\ModerationTweetSourceInterface;
use Twitter\Tweet\Application\Moderation\TweetModerationProviderInterface;
use Twitter\Tweet\Application\Moderation\TweetTextTokenEstimator;

#[Group('unit')]
#[CoversClass(ModerationFlushProcessor::class)]
final class ModerationFlushProcessorTest extends TestCase
{
    private ModerationConfig $config;
    private MockObject&ModerationTweetSourceInterface $tweetSource;
    private TweetTextTokenEstimator $tokenEstimator;
    private ModerationBatchBuilder $batchBuilder;
    private MockObject&ModerationRateLimiterInterface $rateLimiter;
    private MockObject&TweetModerationProviderInterface $provider;
    private MockObject&ModerationStatusUpdaterInterface $statusUpdater;
    private MockObject&ModeratedTweetsCacheInvalidatorInterface $cacheInvalidator;
    private ModerationFlushProcessor $processor;

    protected function setUp(): void
    {
        $this->config = new ModerationConfig(
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
            redisRateReqPrefix: 'moderation:rate:req:',
            redisRateTokPrefix: 'moderation:rate:tok:',
            redisRateReqDayPrefix: 'moderation:rate:day:',
            workerIdleSleepMs: 1000,
            workerLockTtlSeconds: 30,
            workerMaxFetchItems: 100,
            httpTimeout: 10,
            httpMaxDuration: 15,
        );

        $this->tweetSource = $this->createMock(ModerationTweetSourceInterface::class);
        $this->tokenEstimator = new TweetTextTokenEstimator();
        $this->batchBuilder = new ModerationBatchBuilder($this->config);
        $this->rateLimiter = $this->createMock(ModerationRateLimiterInterface::class);
        $this->provider = $this->createMock(TweetModerationProviderInterface::class);
        $this->statusUpdater = $this->createMock(ModerationStatusUpdaterInterface::class);
        $this->cacheInvalidator = $this->createMock(ModeratedTweetsCacheInvalidatorInterface::class);

        $this->processor = new ModerationFlushProcessor(
            $this->config,
            $this->tweetSource,
            $this->tokenEstimator,
            $this->batchBuilder,
            $this->rateLimiter,
            $this->provider,
            $this->statusUpdater,
            $this->cacheInvalidator,
        );
    }

    #[Test]
    public function returnsEmptyReportWhenNoPendingCandidates(): void
    {
        $this->tweetSource->expects(self::once())
            ->method('findPending')
            ->with(100)
            ->willReturn([]);

        $report = $this->processor->processOnce();

        self::assertFalse($report->hasWork);
        self::assertSame(0, $report->totalProcessed);
    }

    #[Test]
    public function returnsRateLimitDeniedReportWhenDenied(): void
    {
        $candidate = new ModerationTweetCandidate('tweet-1', 'Hello', 1, 'user-1');
        $this->tweetSource->method('findPending')->willReturn([$candidate]);

        $this->rateLimiter->expects(self::once())
            ->method('reserveCapacity')
            ->willReturn(ModerationRateLimitResult::denied('RPM exceeded'));

        $report = $this->processor->processOnce();

        self::assertTrue($report->hasWork);
        self::assertTrue($report->rateLimitDenied);
        self::assertSame('RPM exceeded', $report->rateLimitReason);
        self::assertSame(1, $report->totalProcessed);
    }

    #[Test]
    public function processesBatchAndInvalidatesApprovedAuthorFeeds(): void
    {
        $candidate = new ModerationTweetCandidate('tweet-1', 'Approved text', 1, 'user-1');
        $this->tweetSource->method('findPending')->willReturn([$candidate]);
        $this->rateLimiter->method('reserveCapacity')->willReturn(ModerationRateLimitResult::allowed());

        $decision = new ModerationDecision('tweet-1', true, 1, null, [], 'user-1');
        $this->provider->expects(self::once())
            ->method('moderateBatch')
            ->willReturn([$decision]);

        $this->statusUpdater->expects(self::once())
            ->method('apply')
            ->with([$decision])
            ->willReturn([$decision]);

        $this->cacheInvalidator->expects(self::once())
            ->method('invalidateAuthorFeeds')
            ->with(['user-1']);

        $report = $this->processor->processOnce();

        self::assertTrue($report->hasWork);
        self::assertFalse($report->rateLimitDenied);
        self::assertSame(1, $report->totalProcessed);
        self::assertSame(1, $report->approvedCount);
        self::assertSame(0, $report->rejectedCount);
    }

    #[Test]
    public function doesNotInvalidateCacheWhenVersionMismatchRowSkipped(): void
    {
        $candidate = new ModerationTweetCandidate('tweet-1', 'Old text', 1, 'user-1');
        $this->tweetSource->method('findPending')->willReturn([$candidate]);
        $this->rateLimiter->method('reserveCapacity')->willReturn(ModerationRateLimitResult::allowed());

        $decision = new ModerationDecision('tweet-1', true, 1, null, [], 'user-1');
        $this->provider->method('moderateBatch')->willReturn([$decision]);

        // Status updater returns empty list because row version in DB was already updated (version mismatch)
        $this->statusUpdater->method('apply')->willReturn([]);

        $this->cacheInvalidator->expects(self::never())
            ->method('invalidateAuthorFeeds');

        $report = $this->processor->processOnce();

        self::assertSame(1, $report->totalProcessed);
        self::assertSame(1, $report->approvedCount);
    }

    #[Test]
    public function handlesPoisonTweetOnThirdFailure(): void
    {
        $candidate = new ModerationTweetCandidate('poison-tweet', 'Malicious payload', 1, 'user-1');
        $this->tweetSource->method('findPending')->willReturn([$candidate]);
        $this->rateLimiter->method('reserveCapacity')->willReturn(ModerationRateLimitResult::allowed());

        $this->provider->method('moderateBatch')->willThrowException(new RuntimeException('OpenAI 500 internal error'));

        // First failure
        $report1 = $this->processor->processOnce();
        self::assertSame('OpenAI 500 internal error', $report1->errorMessage);
        self::assertSame([], $report1->poisonTweetIds);

        // Second failure
        $report2 = $this->processor->processOnce();
        self::assertSame([], $report2->poisonTweetIds);

        // Third failure: statusUpdater->markFailed should be called
        $this->statusUpdater->expects(self::once())
            ->method('markFailed')
            ->with(['poison-tweet']);

        $report3 = $this->processor->processOnce();
        self::assertSame(['poison-tweet'], $report3->poisonTweetIds);
    }

    #[Test]
    public function doesNotTreat429RateLimitAsPoisonPill(): void
    {
        $candidate = new ModerationTweetCandidate('tweet-1', 'Valid text', 1, 'user-1');
        $this->tweetSource->method('findPending')->willReturn([$candidate]);
        $this->rateLimiter->method('reserveCapacity')->willReturn(ModerationRateLimitResult::allowed());

        $this->provider->method('moderateBatch')
            ->willThrowException(new RuntimeException('OpenAI rate limit exceeded (Retry-After: 5 seconds).', 429));

        $this->statusUpdater->expects(self::never())
            ->method('markFailed');

        for ($i = 0; $i < 5; ++$i) {
            $report = $this->processor->processOnce();
            self::assertSame('OpenAI rate limit exceeded (Retry-After: 5 seconds).', $report->errorMessage);
            self::assertSame([], $report->poisonTweetIds);
        }
    }
}
