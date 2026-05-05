<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Moderation;

use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationMode;

final readonly class ModerationConfigFactory
{
    public function __construct(
        private bool $enabled,
        private string $mode,
        private string $provider,
        private string $model,
        private ?string $apiKey,

        private int $batchMaxItems,
        private int $batchSoftTokenCap,
        private int $batchHardTokenCap,
        private int $batchFlushIntervalMs,

        private int $limitRpm,
        private int $limitTpm,
        private int $limitRpd,

        private int $demoApprovePercent,

        private string $redisQueueKey,
        private string $redisFlushLockKey,
        private string $redisRateReqPrefix,
        private string $redisRateTokPrefix,
        private string $redisRateReqDayPrefix,

        private int $workerIdleSleepMs,
        private int $workerLockTtlSeconds,
        private int $workerMaxFetchItems,
    ) {}

    public function create(): ModerationConfig
    {
        return new ModerationConfig(
            enabled: $this->enabled,
            mode: ModerationMode::fromString($this->mode),
            provider: $this->provider,
            model: $this->model,
            apiKey: $this->normalizeApiKey($this->apiKey),

            batchMaxItems: $this->batchMaxItems,
            batchSoftTokenCap: $this->batchSoftTokenCap,
            batchHardTokenCap: $this->batchHardTokenCap,
            batchFlushIntervalMs: $this->batchFlushIntervalMs,

            limitRpm: $this->limitRpm,
            limitTpm: $this->limitTpm,
            limitRpd: $this->limitRpd,

            demoApprovePercent: $this->demoApprovePercent,

            redisQueueKey: $this->redisQueueKey,
            redisFlushLockKey: $this->redisFlushLockKey,
            redisRateReqPrefix: $this->redisRateReqPrefix,
            redisRateTokPrefix: $this->redisRateTokPrefix,
            redisRateReqDayPrefix: $this->redisRateReqDayPrefix,

            workerIdleSleepMs: $this->workerIdleSleepMs,
            workerLockTtlSeconds: $this->workerLockTtlSeconds,
            workerMaxFetchItems: $this->workerMaxFetchItems,
        );
    }

    private function normalizeApiKey(?string $apiKey): ?string
    {
        if (null === $apiKey) {
            return null;
        }

        $apiKey = trim($apiKey);

        return '' === $apiKey ? null : $apiKey;
    }
}
