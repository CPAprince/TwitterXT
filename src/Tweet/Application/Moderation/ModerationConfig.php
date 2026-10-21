<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

use InvalidArgumentException;

final readonly class ModerationConfig
{
    public function __construct(
        public bool $enabled,
        public ModerationMode $mode,
        public string $provider,
        public string $model,
        public ?string $apiKey,

        public int $batchMaxItems,
        public int $batchSoftTokenCap,
        public int $batchHardTokenCap,
        public int $batchFlushIntervalMs,

        public int $limitRpm,
        public int $limitTpm,
        public int $limitRpd,

        public int $demoApprovePercent,

        public string $redisQueueKey,
        public string $redisFlushLockKey,
        public string $redisRateReqPrefix,
        public string $redisRateTokPrefix,
        public string $redisRateReqDayPrefix,

        public int $workerIdleSleepMs,
        public int $workerLockTtlSeconds,
        public int $workerMaxFetchItems,
    ) {
        $this->assertValid();
    }

    private function assertValid(): void
    {
        if ('' === $this->provider) {
            throw new InvalidArgumentException('Moderation provider must not be empty.');
        }

        if ('' === $this->model) {
            throw new InvalidArgumentException('Moderation model must not be empty.');
        }

        if ($this->batchMaxItems <= 0) {
            throw new InvalidArgumentException('moderation.batch.max_items must be greater than 0.');
        }

        if ($this->batchSoftTokenCap <= 0) {
            throw new InvalidArgumentException('moderation.batch.soft_token_cap must be greater than 0.');
        }

        if ($this->batchHardTokenCap <= 0) {
            throw new InvalidArgumentException('moderation.batch.hard_token_cap must be greater than 0.');
        }

        if ($this->batchSoftTokenCap > $this->batchHardTokenCap) {
            throw new InvalidArgumentException('moderation.batch.soft_token_cap must be less than or equal to hard_token_cap.');
        }

        if ($this->batchFlushIntervalMs <= 0) {
            throw new InvalidArgumentException('moderation.batch.flush_interval_ms must be greater than 0.');
        }

        if ($this->limitRpm <= 0 || $this->limitTpm <= 0 || $this->limitRpd <= 0) {
            throw new InvalidArgumentException('Moderation limits must be greater than 0.');
        }

        if ($this->demoApprovePercent < 0 || $this->demoApprovePercent > 100) {
            throw new InvalidArgumentException('moderation.demo.approve_percent must be between 0 and 100.');
        }

        if ('' === $this->redisQueueKey || '' === $this->redisFlushLockKey) {
            throw new InvalidArgumentException('Redis moderation keys must not be empty.');
        }

        if ($this->workerIdleSleepMs <= 0) {
            throw new InvalidArgumentException('moderation.worker.idle_sleep_ms must be greater than 0.');
        }

        if ($this->workerLockTtlSeconds <= 0) {
            throw new InvalidArgumentException('moderation.worker.lock_ttl_seconds must be greater than 0.');
        }

        if ($this->workerMaxFetchItems <= 0) {
            throw new InvalidArgumentException('moderation.worker.max_fetch_items must be greater than 0.');
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function requiresLiveApiCall(): bool
    {
        return $this->enabled && $this->mode->isLive();
    }

    public function usesDemoMode(): bool
    {
        return $this->enabled && $this->mode->isDemo();
    }

    public function usesBypassMode(): bool
    {
        return !$this->enabled || $this->mode->isBypass();
    }

    public function hasApiKey(): bool
    {
        return null !== $this->apiKey && '' !== trim($this->apiKey);
    }
}
