<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\UI\CLI;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Lock\Exception\LockExpiredException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationFlushProcessorInterface;
use Twitter\Tweet\Application\Moderation\ModerationFlushReport;
use Twitter\Tweet\Application\Moderation\ModerationMode;
use Twitter\Tweet\UI\CLI\ModerationFlushWorkerCommand;

#[Group('unit')]
#[CoversClass(ModerationFlushWorkerCommand::class)]
final class ModerationFlushWorkerCommandTest extends TestCase
{
    private ModerationConfig $config;
    private MockObject&ModerationFlushProcessorInterface $processor;
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
            redisRateReqPrefix: 'moderation:rate:req:',
            redisRateTokPrefix: 'moderation:rate:tok:',
            redisRateReqDayPrefix: 'moderation:rate:day:',
            workerIdleSleepMs: 1,
            workerLockTtlSeconds: 30,
            workerMaxFetchItems: 100,
            httpTimeout: 10,
            httpMaxDuration: 15,
        );

        $this->processor = $this->createMock(ModerationFlushProcessorInterface::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->lock = $this->createMock(SharedLockInterface::class);
        $this->connection = $this->createMock(Connection::class);
    }

    private function createCommand(): ModerationFlushWorkerCommand
    {
        return new ModerationFlushWorkerCommand(
            $this->config,
            $this->processor,
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

        $report = ModerationFlushReport::success(
            totalProcessed: 1,
            approvedCount: 1,
            rejectedCount: 0,
            estimatedTokens: 25,
            isFullBatch: false,
        );

        $this->processor->expects(self::atLeastOnce())
            ->method('processOnce')
            ->willReturn($report);

        $command = $this->createCommand();
        $output = new BufferedOutput();
        $exitCode = $command->run(new ArrayInput(['--time-limit' => '1']), $output);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Moderated batch: total=1 approved=1 rejected=0', $output->fetch());
    }

    #[Test]
    public function handlesProcessorErrorWithPoisonReports(): void
    {
        $this->lock->method('acquire')->willReturn(true);
        $this->lock->method('isAcquired')->willReturn(true);
        $this->lockFactory->method('createLock')->willReturn($this->lock);

        $errorReport = ModerationFlushReport::failure(
            errorMessage: 'OpenAI timeout',
            poisonTweetIds: ['poison-1'],
        );

        $this->processor->method('processOnce')
            ->willReturn($errorReport);

        $command = $this->createCommand();
        $output = new BufferedOutput();
        $exitCode = $command->run(new ArrayInput(['--time-limit' => '1']), $output);

        self::assertSame(Command::SUCCESS, $exitCode);
        $fetched = $output->fetch();
        self::assertStringContainsString('Moderation provider error: OpenAI timeout', $fetched);
        self::assertStringContainsString('Handled 1 poison tweet(s) marked FAILED: poison-1', $fetched);
    }

    #[Test]
    public function handlesSignalStopsWorker(): void
    {
        $command = $this->createCommand();
        $signal = defined('SIGTERM') ? SIGTERM : 15;
        self::assertFalse($command->handleSignal($signal));
    }
}
