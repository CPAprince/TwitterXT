<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Moderation;

use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\TweetModerationProviderInterface;

final readonly class TweetModerationProviderResolver
{
    public function __construct(
        private ModerationConfig $config,
        private DemoTweetModerationProvider $demoProvider,
        private BypassTweetModerationProvider $bypassProvider,
        private OpenAiTweetModerationProvider $openAiProvider,
    ) {}

    public function resolve(): TweetModerationProviderInterface
    {
        if ($this->config->usesBypassMode()) {
            return $this->bypassProvider;
        }

        if ($this->config->usesDemoMode()) {
            return $this->demoProvider;
        }

        return $this->openAiProvider;
    }
}
