<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

interface TweetModerationProviderInterface
{
    /**
     * @param ModerationBatchItem[] $items
     *
     * @return ModerationDecision[]
     */
    public function moderateBatch(array $items): array;
}
