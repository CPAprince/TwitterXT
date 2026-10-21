<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Moderation;

use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationDecision;
use Twitter\Tweet\Application\Moderation\TweetModerationProviderInterface;

final readonly class DemoTweetModerationProvider implements TweetModerationProviderInterface
{
    public function __construct(
        private ModerationConfig $config,
    ) {}

    public function moderateBatch(array $items): array
    {
        $decisions = [];

        foreach ($items as $item) {
            $approved = $this->isApprovedByProbability($this->config->demoApprovePercent);

            $decisions[] = new ModerationDecision(
                tweetId: $item->tweetId,
                approved: $approved,
                moderationVersion: $item->moderationVersion,
                reason: $approved ? null : 'Rejected in demo mode by probability policy.',
                categories: [],
            );
        }

        return $decisions;
    }

    private function isApprovedByProbability(int $approvePercent): bool
    {
        if ($approvePercent <= 0) {
            return false;
        }

        if ($approvePercent >= 100) {
            return true;
        }

        return random_int(1, 100) <= $approvePercent;
    }
}
