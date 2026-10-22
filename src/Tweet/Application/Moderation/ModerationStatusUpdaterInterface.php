<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

interface ModerationStatusUpdaterInterface
{
    /**
     * @param list<ModerationDecision> $decisions
     *
     * @return list<ModerationDecision> The decisions that actually updated a row in the database
     */
    public function apply(array $decisions): array;

    /**
     * @param list<string> $tweetIds
     */
    public function markFailed(array $tweetIds): void;
}
