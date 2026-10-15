<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Moderation;

use Twitter\Tweet\Application\Moderation\ModerationDecision;
use Twitter\Tweet\Application\Moderation\TweetModerationProviderInterface;

final class BypassTweetModerationProvider implements TweetModerationProviderInterface
{
    public function moderateBatch(array $items): array
    {
        $decisions = [];

        foreach ($items as $item) {
            $decisions[] = new ModerationDecision(
                tweetId: $item->tweetId,
                approved: true,
                moderationVersion: $item->moderationVersion,
                reason: null,
                categories: [],
            );
        }

        return $decisions;
    }
}
