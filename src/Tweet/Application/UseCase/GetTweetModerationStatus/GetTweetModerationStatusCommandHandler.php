<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\GetTweetModerationStatus;

use Override;
use Twitter\Tweet\Domain\Tweet\Exception\TweetAccessDeniedException;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

final readonly class GetTweetModerationStatusCommandHandler implements GetTweetModerationStatusCommandHandlerInterface
{
    public function __construct(
        private TweetRepository $tweetRepository,
    ) {}

    /**
     * @throws TweetNotFoundException
     * @throws TweetAccessDeniedException
     */
    #[Override]
    public function handle(GetTweetModerationStatusCommand $command): GetTweetModerationStatusResponse
    {
        $tweet = $this->tweetRepository->getById($command->tweetId);

        if ($tweet->userId() !== $command->userId) {
            throw new TweetAccessDeniedException($command->userId);
        }

        $status = match ($tweet->moderationStatus()) {
            Tweet::MODERATION_APPROVED => 'approved',
            Tweet::MODERATION_REJECTED => 'rejected',
            default => 'pending',
        };

        return new GetTweetModerationStatusResponse(
            tweetId: $tweet->id(),
            status: $status,
        );
    }
}
