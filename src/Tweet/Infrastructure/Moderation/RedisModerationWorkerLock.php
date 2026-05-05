<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Moderation;

use Redis;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationWorkerLockInterface;

final readonly class RedisModerationWorkerLock implements ModerationWorkerLockInterface
{
    private string $token;

    public function __construct(
        private Redis $redis,
        private ModerationConfig $config,
    ) {
        $this->token = bin2hex(random_bytes(16));
    }

    public function acquire(): bool
    {
        return (bool) $this->redis->set(
            $this->config->redisFlushLockKey,
            $this->token,
            ['nx', 'ex' => $this->config->workerLockTtlSeconds]
        );
    }

    public function refresh(): bool
    {
        $currentValue = $this->redis->get($this->config->redisFlushLockKey);

        if (!is_string($currentValue) || $currentValue !== $this->token) {
            return false;
        }

        return $this->redis->expire(
            $this->config->redisFlushLockKey,
            $this->config->workerLockTtlSeconds
        );
    }

    public function release(): void
    {
        $currentValue = $this->redis->get($this->config->redisFlushLockKey);

        if (is_string($currentValue) && $currentValue === $this->token) {
            $this->redis->del($this->config->redisFlushLockKey);
        }
    }
}
