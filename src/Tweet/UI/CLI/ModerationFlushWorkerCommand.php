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
use Symfony\Component\Lock\Exception\ExceptionInterface;
use Symfony\Component\Lock\LockFactory;
use Throwable;
use Twitter\Tweet\Application\Moderation\ModeratedTweetsCacheInvalidatorInterface;
use Twitter\Tweet\Application\Moderation\ModerationBatch;
use Twitter\Tweet\Application\Moderation\ModerationBatchBuilder;
use Twitter\Tweet\Application\Moderation\ModerationBatchItem;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationDecision;
use Twitter\Tweet\Application\Moderation\ModerationQueueInterface;
use Twitter\Tweet\Application\Moderation\ModerationRateLimiterInterface;
use Twitter\Tweet\Application\Moderation\ModerationStatusUpdaterInterface;
use Twitter\Tweet\Application\Moderation\ModerationTweetCandidate;
use Twitter\Tweet\Application\Moderation\ModerationTweetSourceInterface;
use Twitter\Tweet\Application\Moderation\TweetModerationProviderInterface;
use Twitter\Tweet\Application\Moderation\TweetTextTokenEstimator;

#[AsCommand(
    name: 'app:moderation:flush-worker',
    description: 'Flushes queued tweets to moderation in batches.'
)]
final class ModerationFlushWorkerCommand extends Command implements SignalableCommandInterface
{
    private const int MAX_POISON_ATTEMPTS = 3;
    private const int MAX_ERROR_BACKOFF_SECONDS = 60;

    private bool $shouldStop = false;

    /** @var array<string, int> */
    private array $failedAttempts = [];

    public function __construct(
        private readonly ModerationConfig $config,
        private readonly ModerationQueueInterface $queue,
        private readonly ModerationTweetSourceInterface $tweetSource,
        private readonly TweetTextTokenEstimator $tokenEstimator,
        private readonly ModerationBatchBuilder $batchBuilder,
        private readonly ModerationRateLimiterInterface $rateLimiter,
        private readonly TweetModerationProviderInterface $provider,
        private readonly ModerationStatusUpdaterInterface $statusUpdater,
        private readonly ModeratedTweetsCacheInvalidatorInterface $cacheInvalidator,
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
                } catch (ExceptionInterface) {
                    $output->writeln('<error>Moderation worker lock lost. Stopping worker.</error>');

                    return Command::FAILURE;
                }

                if (!$lock->isAcquired()) {
                    $output->writeln('<error>Moderation worker lock lost. Stopping worker.</error>');

                    return Command::FAILURE;
                }

                $tickResult = $this->runTick($output);

                if ($tickResult['error']) {
                    sleep($errorBackoff);
                    $errorBackoff = min(self::MAX_ERROR_BACKOFF_SECONDS, $errorBackoff * 2);
                } else {
                    $errorBackoff = 1;

                    if ($tickResult['rateLimitDenied']) {
                        $now = new DateTimeImmutable();
                        $secondsUntilNextMinute = max(1, 60 - (int) $now->format('s'));
                        $output->writeln(sprintf(
                            '<comment>Rate limit reached. Sleeping %ds until next minute.</comment>',
                            $secondsUntilNextMinute
                        ));
                        sleep($secondsUntilNextMinute);
                    } elseif ($tickResult['fullBatch']) {
                        // Continue immediately when last batch was full
                        usleep(0);
                    } else {
                        usleep($this->config->workerIdleSleepMs * 1000);
                    }
                }
            }

            return Command::SUCCESS;
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{error: bool, rateLimitDenied: bool, fullBatch: bool}
     */
    private function runTick(OutputInterface $output): array
    {
        try {
            return $this->tick($output);
        } catch (DBALException $e) {
            $output->writeln(sprintf('<error>Database error in worker: %s</error>', $e->getMessage()));
            try {
                $this->connection->close();
            } catch (Throwable) {
                // Ignore error during close
            }

            return ['error' => true, 'rateLimitDenied' => false, 'fullBatch' => false];
        } catch (Throwable $e) {
            $output->writeln(sprintf('<error>Moderation worker error: %s</error>', $e->getMessage()));

            return ['error' => true, 'rateLimitDenied' => false, 'fullBatch' => false];
        }
    }

    /**
     * @param list<ModerationTweetCandidate> $candidates
     *
     * @return list<ModerationBatchItem>
     */
    private function toBatchItems(array $candidates): array
    {
        $items = [];

        foreach ($candidates as $candidate) {
            $items[] = new ModerationBatchItem(
                tweetId: $candidate->tweetId,
                text: $candidate->text,
                estimatedTokens: $this->tokenEstimator->estimate($candidate->text),
                moderationVersion: $candidate->moderationVersion,
                userId: $candidate->userId,
            );
        }

        return $items;
    }

    /**
     * @param list<ModerationDecision> $decisions
     *
     * @return array{list<string>, list<string>}
     */
    private function splitDecisionIds(array $decisions): array
    {
        $approvedIds = [];
        $rejectedIds = [];

        foreach ($decisions as $decision) {
            if ($decision->approved) {
                $approvedIds[] = $decision->tweetId;
            } else {
                $rejectedIds[] = $decision->tweetId;
            }
        }

        return [$approvedIds, $rejectedIds];
    }

    /**
     * @return array{error: bool, rateLimitDenied: bool, fullBatch: bool}
     */
    private function tick(OutputInterface $output): array
    {
        $queuedIds = $this->queue->peek($this->config->workerMaxFetchItems);

        if ([] === $queuedIds) {
            return ['error' => false, 'rateLimitDenied' => false, 'fullBatch' => false];
        }

        $candidates = $this->tweetSource->findByIds($queuedIds);

        if ([] === $candidates) {
            $this->queue->remove($queuedIds);

            $output->writeln(sprintf(
                '<comment>Removed %d stale tweet ids from moderation queue.</comment>',
                count($queuedIds)
            ));

            return ['error' => false, 'rateLimitDenied' => false, 'fullBatch' => false];
        }

        $batchItems = $this->toBatchItems($candidates);
        $batch = $this->batchBuilder->build($batchItems);

        if (null === $batch) {
            return ['error' => false, 'rateLimitDenied' => false, 'fullBatch' => false];
        }

        $rateLimitResult = $this->rateLimiter->reserveCapacity($batch->estimatedTokens);

        if (!$rateLimitResult->allowed) {
            $output->writeln(sprintf(
                '<comment>Moderation rate limit denied batch. reason=%s size=%d tokens=%d</comment>',
                $rateLimitResult->reason ?? 'unknown',
                $batch->count(),
                $batch->estimatedTokens,
            ));

            return ['error' => false, 'rateLimitDenied' => true, 'fullBatch' => false];
        }

        try {
            $decisions = $this->provider->moderateBatch($batch->items);
        } catch (Throwable $providerException) {
            $this->handleBatchFailure($batch, $output);
            throw $providerException;
        }

        [$approvedIds, $rejectedIds] = $this->splitDecisionIds($decisions);

        $appliedDecisions = $this->statusUpdater->apply($decisions);

        $approvedAuthorIds = [];
        foreach ($appliedDecisions as $decision) {
            if ($decision->approved && '' !== $decision->userId) {
                $approvedAuthorIds[] = $decision->userId;
            }
        }

        if ([] !== $approvedAuthorIds) {
            $this->cacheInvalidator->invalidateAuthorFeeds($approvedAuthorIds);
        }

        $processedIds = array_merge($approvedIds, $rejectedIds);
        $this->queue->remove($processedIds);

        foreach ($processedIds as $processedId) {
            unset($this->failedAttempts[$processedId]);
        }

        $now = new DateTimeImmutable();

        $output->writeln(sprintf(
            '[ %s ] <info>Moderated batch: total=%d approved=%d rejected=%d tokens=%d</info>',
            $now->format('Y-m-d H:i:s.v'),
            count($processedIds),
            count($approvedIds),
            count($rejectedIds),
            $batch->estimatedTokens,
        ));

        $isFullBatch = count($processedIds) >= $this->config->batchMaxItems;

        return ['error' => false, 'rateLimitDenied' => false, 'fullBatch' => $isFullBatch];
    }

    private function handleBatchFailure(ModerationBatch $batch, OutputInterface $output): void
    {
        $poisonIds = [];

        foreach ($batch->items as $item) {
            $this->failedAttempts[$item->tweetId] = ($this->failedAttempts[$item->tweetId] ?? 0) + 1;

            if ($this->failedAttempts[$item->tweetId] >= self::MAX_POISON_ATTEMPTS) {
                $poisonIds[] = $item->tweetId;
                unset($this->failedAttempts[$item->tweetId]);
            }
        }

        if ([] !== $poisonIds) {
            $output->writeln(sprintf(
                '<error>Handling %d poison tweet(s) (>= %d failures): %s</error>',
                count($poisonIds),
                self::MAX_POISON_ATTEMPTS,
                implode(', ', $poisonIds),
            ));

            $this->statusUpdater->markFailed($poisonIds);
            $this->queue->remove($poisonIds);
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
