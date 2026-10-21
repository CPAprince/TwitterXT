<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

use Override;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;

final readonly class TweetModerationPolicy implements TweetModerationPolicyInterface
{
    public function __construct(
        private ModerationConfig $config,
        private ModerationQueueInterface $moderationQueue,
    ) {}

    #[Override]
    public function onContentWritten(Tweet $tweet): void
    {
        if ($this->config->isBypass()) {
            $tweet->approveModeration();

            return;
        }

        $this->moderationQueue->enqueue($tweet->id());
    }
}
