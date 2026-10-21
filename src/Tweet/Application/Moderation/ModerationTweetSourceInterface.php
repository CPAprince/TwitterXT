<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

interface ModerationTweetSourceInterface
{
    /**
     * @param string[] $tweetIds
     *
     * @return ModerationTweetCandidate[]
     */
    public function findByIds(array $tweetIds): array;
}
