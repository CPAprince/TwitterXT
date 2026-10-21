<?php

declare(strict_types=1);

namespace Twitter\Tweet\UI\CLI;

use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use Twitter\Tweet\Application\Moderation\ModerationBatchBuilder;
use Twitter\Tweet\Application\Moderation\ModerationBatchItem;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationQueueInterface;
use Twitter\Tweet\Application\Moderation\ModerationRateLimiterInterface;
use Twitter\Tweet\Application\Moderation\ModerationStatusUpdaterInterface;
use Twitter\Tweet\Application\Moderation\ModerationTweetSourceInterface;
use Twitter\Tweet\Application\Moderation\ModerationWorkerLockInterface;
use Twitter\Tweet\Application\Moderation\TweetModerationProviderInterface;
use Twitter\Tweet\Application\Moderation\TweetTextTokenEstimator;

#[AsCommand(
    name: 'app:moderation:flush-worker',
    description: 'Flushes queued tweets to moderation in batches.'
)]
final class ModerationFlushWorkerCommand extends Command
{
    public function __construct(
        private readonly ModerationConfig $config,
        private readonly ModerationQueueInterface $queue,
        private readonly ModerationTweetSourceInterface $tweetSource,
        private readonly TweetTextTokenEstimator $tokenEstimator,
        private readonly ModerationBatchBuilder $batchBuilder,
        private readonly ModerationRateLimiterInterface $rateLimiter,
        private readonly TweetModerationProviderInterface $provider,
        private readonly ModerationStatusUpdaterInterface $statusUpdater,
        private readonly ModerationWorkerLockInterface $workerLock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->workerLock->acquire()) {
            $output->writeln('<comment>Moderation flush worker is already running.</comment>');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            'Moderation flush worker started. mode=%s model=%s',
            $this->config->mode->value,
            $this->config->model,
        ));

        try {
            while (true) {
                if (!$this->workerLock->refresh()) {
                    $output->writeln('<error>Moderation worker lock lost. Stopping worker.</error>');

                    return Command::FAILURE;
                }

                try {
                    $this->tick($output);
                } catch (Throwable $e) {
                    $output->writeln(sprintf(
                        '<error>Moderation worker error: %s</error>',
                        $e->getMessage()
                    ));
                }

                usleep($this->config->workerIdleSleepMs * 1000);
            }
        } finally {
            $this->workerLock->release();
        }
    }

    private function toBatchItems(array $candidates): array
    {
        $items = [];

        foreach ($candidates as $candidate) {
            $items[] = new ModerationBatchItem(
                tweetId: $candidate->tweetId,
                text: $candidate->text,
                estimatedTokens: $this->tokenEstimator->estimate($candidate->text),
                moderationVersion: $candidate->moderationVersion,
            );
        }

        return $items;
    }

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

    private function tick(OutputInterface $output): void
    {
        $queuedIds = $this->queue->peek($this->config->workerMaxFetchItems);

        if ([] === $queuedIds) {
            return;
        }

        $candidates = $this->tweetSource->findByIds($queuedIds);

        if ([] === $candidates) {
            $this->queue->remove($queuedIds);

            $output->writeln(sprintf(
                '<comment>Removed %d stale tweet ids from moderation queue.</comment>',
                count($queuedIds)
            ));

            return;
        }

        $batchItems = $this->toBatchItems($candidates);
        $batch = $this->batchBuilder->build($batchItems);

        if (null === $batch) {
            return;
        }

        $rateLimitResult = $this->rateLimiter->reserveCapacity($batch->estimatedTokens);

        if (!$rateLimitResult->allowed) {
            $output->writeln(sprintf(
                '<comment>Moderation rate limit denied batch. reason=%s size=%d tokens=%d</comment>',
                $rateLimitResult->reason ?? 'unknown',
                $batch->count(),
                $batch->estimatedTokens,
            ));

            return;
        }

        $decisions = $this->provider->moderateBatch($batch->items);

        [$approvedIds, $rejectedIds] = $this->splitDecisionIds($decisions);

        $this->statusUpdater->apply($decisions);

        $processedIds = array_merge($approvedIds, $rejectedIds);
        $this->queue->remove($processedIds);

        $now = new DateTimeImmutable();

        $output->writeln(sprintf(
            '[ %s ] <info>Moderated batch: total=%d approved=%d rejected=%d tokens=%d</info>',
            $now->format('Y-m-d H:i:s.v'),
            count($processedIds),
            count($approvedIds),
            count($rejectedIds),
            $batch->estimatedTokens,
        ));
    }
}
