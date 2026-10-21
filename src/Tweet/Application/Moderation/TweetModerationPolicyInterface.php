<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

use Twitter\Tweet\Domain\Tweet\Model\Tweet;

interface TweetModerationPolicyInterface
{
    public function onContentWritten(Tweet $tweet): void;
}
