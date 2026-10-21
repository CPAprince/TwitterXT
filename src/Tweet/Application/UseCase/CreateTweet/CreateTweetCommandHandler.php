<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\CreateTweet;

use Override;
use Twitter\Tweet\Application\Moderation\TweetModerationPolicyInterface;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

final readonly class CreateTweetCommandHandler implements CreateTweetCommandHandlerInterface
{
    public function __construct(
        private TweetRepository $tweetRepository,
        private TweetModerationPolicyInterface $moderationPolicy,
    ) {}

    #[Override]
    public function handle(CreateTweetCommand $command): CreateTweetCommandResult
    {
        $tweet = Tweet::create($command->userId, $command->content);
        $this->moderationPolicy->onContentWritten($tweet);

        $this->tweetRepository->add($tweet);

        $status = match ($tweet->moderationStatus()) {
            Tweet::MODERATION_APPROVED => 'approved',
            Tweet::MODERATION_REJECTED => 'rejected',
            default => 'pending',
        };

        return new CreateTweetCommandResult(
            $tweet->id(),
            $tweet->createdAt(),
            $tweet->updatedAt(),
            $status,
        );
    }
}
