<?php

declare(strict_types=1);

namespace Twitter\Tweet\UI\CLI;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\Exception\ExceptionInterface as LockExceptionInterface;
use Symfony\Component\Lock\LockFactory;
use Throwable;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationFlushProcessorInterface;

#[AsCommand(
    name: 'app:moderation:flush-worker',
    description: 'Flushes queued tweets to moderation in batches.'
)]
final class ModerationFlushWorkerCommand extends Command implements SignalableCommandInterface
{
    private const int MAX_ERROR_BACKOFF_SECONDS = 60;

    private bool $shouldStop = false;

    public function __construct(
        private readonly ModerationConfig $config,
        private readonly ModerationFlushProcessorInterface $processor,
        private readonly LockFactory $lockFactory,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('time-limit', null, InputOption::VALUE_REQUIRED, 'Maximum runtime in seconds.', 0)
            ->addOption('memory-limit', null, InputOption::VALUE_REQUIRED, 'Maximum memory usage (e.g. 128M).', null);
    }

    /**
     * @return list<int>
     */
    public function getSubscribedSignals(): array
    {
        $signals = [];
        if (defined('SIGTERM')) {
            $signals[] = SIGTERM;
        }
        if (defined('SIGINT')) {
            $signals[] = SIGINT;
        }

        return $signals;
    }

    public function handleSignal(int $signal, false|int $previousExitCode = 0): false|int
    {
        $this->shouldStop = true;

        return false;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $lock = $this->lockFactory->createLock('moderation_flush_worker', (float) $this->config->workerLockTtlSeconds);

        if (!$lock->acquire()) {
            $output->writeln('<comment>Moderation flush worker is already running.</comment>');

            return Command::SUCCESS;
        }

        $timeLimit = (int) $input->getOption('time-limit');
        $memoryLimit = $this->parseMemoryLimit($input->getOption('memory-limit'));
        $startTime = microtime(true);

        $output->writeln(sprintf(
            'Moderation flush worker started. mode=%s model=%s lock_ttl=%ds',
            $this->config->mode->value,
            $this->config->model,
            $this->config->workerLockTtlSeconds,
        ));

        $errorBackoff = 1;

        try {
            while (!$this->shouldStop) {
                if ($timeLimit > 0 && (microtime(true) - $startTime) >= $timeLimit) {
                    $output->writeln('<info>Time limit reached. Stopping worker.</info>');
                    break;
                }

                if ($memoryLimit > 0 && memory_get_usage(true) >= $memoryLimit) {
                    $output->writeln('<info>Memory limit reached. Stopping worker.</info>');
                    break;
                }

                try {
                    $lock->refresh();
                } catch (LockExceptionInterface) {
                    $output->writeln('<error>Moderation worker lock lost. Stopping worker.</error>');

                    return Command::FAILURE;
                }

                if (!$lock->isAcquired()) {
                    $output->writeln('<error>Moderation worker lock lost. Stopping worker.</error>');

                    return Command::FAILURE;
                }

                try {
                    $report = $this->processor->processOnce();
                } catch (DBALException $e) {
                    $output->writeln(sprintf('<error>Database error in worker: %s</error>', $e->getMessage()));
                    try {
                        $this->connection->close();
                    } catch (Throwable) {
                        // Ignore close error
                    }

                    sleep($errorBackoff);
                    $errorBackoff = min(self::MAX_ERROR_BACKOFF_SECONDS, $errorBackoff * 2);
                    continue;
                } catch (Throwable $e) {
                    $output->writeln(sprintf('<error>Moderation worker error: %s</error>', $e->getMessage()));
                    sleep($errorBackoff);
                    $errorBackoff = min(self::MAX_ERROR_BACKOFF_SECONDS, $errorBackoff * 2);
                    continue;
                }

                if (null !== $report->errorMessage) {
                    $output->writeln(sprintf('<error>Moderation provider error: %s</error>', $report->errorMessage));
                    if ([] !== $report->poisonTweetIds) {
                        $output->writeln(sprintf(
                            '<error>Handled %d poison tweet(s) marked FAILED: %s</error>',
                            count($report->poisonTweetIds),
                            implode(', ', $report->poisonTweetIds),
                        ));
                    }

                    $sleepSeconds = $errorBackoff;
                    if (preg_match('/Retry-After:\s*(\d+)/i', $report->errorMessage, $matches)) {
                        $retryAfter = (int) $matches[1];
                        if ($retryAfter > 0) {
                            $sleepSeconds = min(self::MAX_ERROR_BACKOFF_SECONDS, $retryAfter);
                        }
                    }

                    sleep($sleepSeconds);
                    $errorBackoff = min(self::MAX_ERROR_BACKOFF_SECONDS, $errorBackoff * 2);
                    continue;
                }

                $errorBackoff = 1;

                if ($report->rateLimitDenied) {
                    $now = new DateTimeImmutable();
                    $secondsUntilNextMinute = max(1, 60 - (int) $now->format('s'));
                    $output->writeln(sprintf(
                        '<comment>Moderation rate limit denied batch (%s). Sleeping %ds until next minute.</comment>',
                        $report->rateLimitReason ?? 'limit reached',
                        $secondsUntilNextMinute
                    ));
                    sleep($secondsUntilNextMinute);
                } elseif ($report->hasWork) {
                    $now = new DateTimeImmutable();
                    $output->writeln(sprintf(
                        '[ %s ] <info>Moderated batch: total=%d approved=%d rejected=%d tokens=%d</info>',
                        $now->format('Y-m-d H:i:s.v'),
                        $report->totalProcessed,
                        $report->approvedCount,
                        $report->rejectedCount,
                        $report->estimatedTokens,
                    ));

                    if ($report->isFullBatch) {
                        usleep(0);
                    } else {
                        usleep($this->config->batchFlushIntervalMs * 1000);
                    }
                } else {
                    usleep($this->config->workerIdleSleepMs * 1000);
                }
            }

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    private function parseMemoryLimit(mixed $memoryLimit): int
    {
        if (!is_string($memoryLimit) || '' === trim($memoryLimit) || '-1' === $memoryLimit) {
            return -1;
        }

        $memoryLimit = trim($memoryLimit);
        $unit = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $value = (int) $memoryLimit;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => (int) $memoryLimit,
        };
    }
}
