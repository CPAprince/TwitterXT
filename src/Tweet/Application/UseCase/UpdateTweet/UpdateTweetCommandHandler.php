<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\UpdateTweet;

use Override;
use Twitter\Tweet\Application\Moderation\TweetModerationPolicyInterface;
use Twitter\Tweet\Domain\Tweet\Exception\TweetAccessDeniedException;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

final readonly class UpdateTweetCommandHandler implements UpdateTweetCommandHandlerInterface
{
    public function __construct(
        private TweetRepository $tweetRepository,
        private TweetModerationPolicyInterface $moderationPolicy,
    ) {}

    /**
     * @throws TweetAccessDeniedException
     * @throws TweetNotFoundException
     */
    #[Override]
    public function handle(UpdateTweetCommand $command): UpdateTweetCommandResult
    {
        $tweet = $this->tweetRepository->getById($command->tweetId);

        if ($command->userId !== $tweet->userId()) {
            throw new TweetAccessDeniedException($command->userId);
        }

        $contentChanged = $tweet->updateContent($command->content);

        if ($contentChanged) {
            $this->moderationPolicy->onContentWritten($tweet);
        }

        $this->tweetRepository->flush();

        $status = match ($tweet->moderationStatus()) {
            Tweet::MODERATION_APPROVED => 'approved',
            Tweet::MODERATION_REJECTED => 'rejected',
            default => 'pending',
        };

        return new UpdateTweetCommandResult($tweet->content(), $tweet->updatedAt(), $status);
    }
}
