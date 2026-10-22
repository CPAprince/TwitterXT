<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

use Throwable;

final class ModerationFlushProcessor implements ModerationFlushProcessorInterface
{
    private const int MAX_POISON_ATTEMPTS = 3;

    /** @var array<string, int> */
    private array $failedAttempts = [];

    public function __construct(
        private readonly ModerationConfig $config,
        private readonly ModerationTweetSourceInterface $tweetSource,
        private readonly TweetTextTokenEstimator $tokenEstimator,
        private readonly ModerationBatchBuilder $batchBuilder,
        private readonly ModerationRateLimiterInterface $rateLimiter,
        private readonly TweetModerationProviderInterface $provider,
        private readonly ModerationStatusUpdaterInterface $statusUpdater,
        private readonly ModeratedTweetsCacheInvalidatorInterface $cacheInvalidator,
    ) {}

    public function processOnce(): ModerationFlushReport
    {
        $candidates = $this->tweetSource->findPending($this->config->workerMaxFetchItems);

        if ([] === $candidates) {
            return ModerationFlushReport::empty();
        }

        $batchItems = $this->toBatchItems($candidates);
        $batch = $this->batchBuilder->build($batchItems);

        if (null === $batch) {
            return ModerationFlushReport::empty();
        }

        $rateLimitResult = $this->rateLimiter->reserveCapacity($batch->estimatedTokens);

        if (!$rateLimitResult->allowed) {
            return ModerationFlushReport::rateLimitDenied(
                $rateLimitResult->reason ?? 'unknown',
                $batch->count(),
                $batch->estimatedTokens,
            );
        }

        try {
            $decisions = $this->provider->moderateBatch($batch->items);
        } catch (Throwable $e) {
            $isTransientRateLimit = 429 === $e->getCode();
            $poisonIds = $isTransientRateLimit ? [] : $this->handleBatchFailure($batch);

            return ModerationFlushReport::failure($e->getMessage(), $poisonIds);
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
            $this->cacheInvalidator->invalidateAuthorFeeds(array_values(array_unique($approvedAuthorIds)));
        }

        $processedIds = array_merge($approvedIds, $rejectedIds);

        foreach ($processedIds as $processedId) {
            unset($this->failedAttempts[$processedId]);
        }

        $isFullBatch = count($candidates) >= $this->config->workerMaxFetchItems
            || count($processedIds) >= $this->config->batchMaxItems;

        return ModerationFlushReport::success(
            totalProcessed: count($processedIds),
            approvedCount: count($approvedIds),
            rejectedCount: count($rejectedIds),
            estimatedTokens: $batch->estimatedTokens,
            isFullBatch: $isFullBatch,
        );
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
     * @return list<string> Poison tweet IDs that exceeded max attempts and were marked failed
     */
    private function handleBatchFailure(ModerationBatch $batch): array
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
            $this->statusUpdater->markFailed($poisonIds);
        }

        return $poisonIds;
    }
}
