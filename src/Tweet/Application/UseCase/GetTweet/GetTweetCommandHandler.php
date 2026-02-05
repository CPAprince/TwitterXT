<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\GetTweet;

use Twitter\Profile\Domain\Profile\Model\ProfileRepository;
use Twitter\Tweet\Application\UseCase\Shared\TweetResponse;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

final readonly class GetTweetCommandHandler
{
    public function __construct(
        private TweetRepository $tweetRepository,
        private ProfileRepository $profileRepository,
    ) {}

    public function handle(GetTweetCommand $command): TweetResponse
    {
        $tweet = $this->tweetRepository->getById($command->tweetId);

        $authorId = $tweet->userId();
        $profile = $this->profileRepository->getByUserId($authorId);

        return new TweetResponse(
            id: $tweet->id(),
            content: $tweet->content(),
            createdAt: $tweet->createdAt(),
            updatedAt: $tweet->updatedAt(),
            authorId: $authorId,
            authorName: $profile->name(),
            likesCount: $tweet->likes(),
        );
    }
}
