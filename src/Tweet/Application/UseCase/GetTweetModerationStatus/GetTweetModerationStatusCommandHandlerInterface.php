<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\GetTweetModerationStatus;

use Twitter\Tweet\Domain\Tweet\Exception\TweetAccessDeniedException;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;

interface GetTweetModerationStatusCommandHandlerInterface
{
    /**
     * @throws TweetNotFoundException
     * @throws TweetAccessDeniedException
     */
    public function handle(GetTweetModerationStatusCommand $command): GetTweetModerationStatusResponse;
}
