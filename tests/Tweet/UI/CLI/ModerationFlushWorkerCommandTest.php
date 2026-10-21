<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\UI\CLI;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Twitter\Tweet\Application\Moderation\ModeratedTweetsCacheInvalidatorInterface;
use Twitter\Tweet\Application\Moderation\ModerationBatchBuilder;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationDecision;
use Twitter\Tweet\Application\Moderation\ModerationMode;
use Twitter\Tweet\Application\Moderation\ModerationQueueInterface;
use Twitter\Tweet\Application\Moderation\ModerationRateLimiterInterface;
use Twitter\Tweet\Application\Moderation\ModerationRateLimitResult;
use Twitter\Tweet\Application\Moderation\ModerationStatusUpdaterInterface;
use Twitter\Tweet\Application\Moderation\ModerationTweetCandidate;
use Twitter\Tweet\Application\Moderation\ModerationTweetSourceInterface;
use Twitter\Tweet\Application\Moderation\TweetModerationProviderInterface;
use Twitter\Tweet\Application\Moderation\TweetTextTokenEstimator;
use Twitter\Tweet\UI\CLI\ModerationFlushWorkerCommand;

#[Group('unit')]
#[CoversClass(ModerationFlushWorkerCommand::class)]
final class ModerationFlushWorkerCommandTest extends TestCase
{
    private ModerationConfig $config;
    private MockObject&ModerationQueueInterface $queue;
    private MockObject&ModerationTweetSourceInterface $tweetSource;
    private TweetTextTokenEstimator $tokenEstimator;
    private ModerationBatchBuilder $batchBuilder;
    private MockObject&ModerationRateLimiterInterface $rateLimiter;
    private MockObject&TweetModerationProviderInterface $provider;
    private MockObject&ModerationStatusUpdaterInterface $statusUpdater;
    private MockObject&ModeratedTweetsCacheInvalidatorInterface $cacheInvalidator;
    private MockObject&LockFactory $lockFactory;
    private MockObject&SharedLockInterface $lock;
    private MockObject&Connection $connection;

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
            redisQueueKey: 'moderation:queue',
            redisFlushLockKey: 'moderation:lock',
            redisRateReqPrefix: 'moderation:rate:req:',
            redisRateTokPrefix: 'moderation:rate:tok:',
            redisRateReqDayPrefix: 'moderation:rate:day:',
            workerIdleSleepMs: 1,
            workerLockTtlSeconds: 30,
            workerMaxFetchItems: 100,
            httpTimeout: 10,
            httpMaxDuration: 15,
        );

        $this->queue = $this->createMock(ModerationQueueInterface::class);
        $this->tweetSource = $this->createMock(ModerationTweetSourceInterface::class);
        $this->tokenEstimator = new TweetTextTokenEstimator();
        $this->batchBuilder = new ModerationBatchBuilder($this->config);
        $this->rateLimiter = $this->createMock(ModerationRateLimiterInterface::class);
        $this->provider = $this->createMock(TweetModerationProviderInterface::class);
        $this->statusUpdater = $this->createMock(ModerationStatusUpdaterInterface::class);
        $this->cacheInvalidator = $this->createMock(ModeratedTweetsCacheInvalidatorInterface::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->lock = $this->createMock(SharedLockInterface::class);
        $this->connection = $this->createMock(Connection::class);
    }

    private function createCommand(): ModerationFlushWorkerCommand
    {
        return new ModerationFlushWorkerCommand(
            $this->config,
            $this->queue,
            $this->tweetSource,
            $this->tokenEstimator,
            $this->batchBuilder,
            $this->rateLimiter,
            $this->provider,
            $this->statusUpdater,
            $this->cacheInvalidator,
            $this->lockFactory,
            $this->connection,
        );
    }

    #[Test]
    public function returnsSuccessImmediatelyWhenLockCannotBeAcquired(): void
    {
        $this->lock->method('acquire')->willReturn(false);
        $this->lockFactory->method('createLock')->willReturn($this->lock);

        $command = $this->createCommand();
        $output = new BufferedOutput();
        $exitCode = $command->run(new ArrayInput([]), $output);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Moderation flush worker is already running', $output->fetch());
    }

    #[Test]
    public function returnsFailureWhenLockRefreshFails(): void
    {
        $this->lock->method('acquire')->willReturn(true);
        $this->lock->method('refresh')->willThrowException(new LockExpiredException('Lock expired'));
        $this->lock->expects(self::once())->method('release');
        $this->lockFactory->method('createLock')->willReturn($this->lock);

        $command = $this->createCommand();
        $output = new BufferedOutput();
        $exitCode = $command->run(new ArrayInput([]), $output);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Moderation worker lock lost', $output->fetch());
    }

    #[Test]
    public function processesBatchSuccessfullyAndStopsOnTimeLimit(): void
    {
        $this->lock->method('acquire')->willReturn(true);
        $this->lock->method('isAcquired')->willReturn(true);
        $this->lockFactory->method('createLock')->willReturn($this->lock);

        $this->queue->method('peek')->willReturnOnConsecutiveCalls(['tweet-1'], []);
        $candidate = new ModerationTweetCandidate(tweetId: 'tweet-1', text: 'Hello world', moderationVersion: 1, userId: 'user-1');
        $this->tweetSource->method('findByIds')->willReturn([$candidate]);

        $this->rateLimiter->method('reserveCapacity')->willReturn(ModerationRateLimitResult::allowed());

        $decision = new ModerationDecision('tweet-1', true, 1, null, [], 'user-1');
        $this->provider->method('moderateBatch')->willReturn([$decision]);

        $this->statusUpdater->method('apply')->willReturn([$decision]);
        $this->cacheInvalidator->expects(self::once())->method('invalidateAuthorFeeds')->with(['user-1']);
        $this->queue->expects(self::once())->method('remove')->with(['tweet-1']);

        $command = $this->createCommand();
        $output = new BufferedOutput();
        // Set time limit to 1s so it runs one iteration and stops
        $exitCode = $command->run(new ArrayInput(['--time-limit' => '1']), $output);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Moderated batch: total=1 approved=1 rejected=0', $output->fetch());
    }

    #[Test]
    public function handlesPoisonTweetAfterThreeFailures(): void
    {
        $this->lock->method('acquire')->willReturn(true);
        $this->lock->method('isAcquired')->willReturn(true);
        $this->lockFactory->method('createLock')->willReturn($this->lock);

        $this->queue->method('peek')->willReturn(['poison-1']);
        $candidate = new ModerationTweetCandidate(tweetId: 'poison-1', text: 'Bad content', moderationVersion: 1, userId: 'user-1');
        $this->tweetSource->method('findByIds')->willReturn([$candidate]);
        $this->rateLimiter->method('reserveCapacity')->willReturn(ModerationRateLimitResult::allowed());

        $this->provider->method('moderateBatch')->willThrowException(new RuntimeException('API error'));

        // Expect markFailed and queue remove on 3rd failure
        $this->statusUpdater->expects(self::once())->method('markFailed')->with(['poison-1']);
        $this->queue->expects(self::once())->method('remove')->with(['poison-1']);

        $command = $this->createCommand();

        // Use reflection to set failedAttempts to 2 for poison-1
        $reflection = new ReflectionClass($command);
        $property = $reflection->getProperty('failedAttempts');
        $property->setAccessible(true);
        $property->setValue($command, ['poison-1' => 2]);

        $output = new BufferedOutput();
        $exitCode = $command->run(new ArrayInput(['--time-limit' => '1']), $output);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Handling 1 poison tweet(s)', $output->fetch());
    }

    #[Test]
    public function handlesSignalStopsWorker(): void
    {
        $command = $this->createCommand();
        $signal = defined('SIGTERM') ? SIGTERM : 15;
        self::assertFalse($command->handleSignal($signal));
    }
}
