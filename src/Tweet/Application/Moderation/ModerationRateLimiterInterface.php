<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

interface ModerationRateLimiterInterface
{
    public function reserveCapacity(int $batchEstimatedTokens): ModerationRateLimitResult;
}
