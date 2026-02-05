<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\GetTweets;

use Twitter\Profile\Domain\Profile\Model\ProfileRepository;
use Twitter\Tweet\Application\UseCase\Shared\TweetResponse;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

final readonly class GetTweetsCommandHandler
{
    public function __construct(
        private TweetRepository $tweetRepository,
        private ProfileRepository $profileRepository,
    ) {}

    public function handle(GetTweetsCommand $command): GetTweetsResponse
    {
        $tweets = $this->tweetRepository->getAllTweets($command->limit, $command->page);
        $tweetResponses = [];

        if ([] === $tweets) {
            return new GetTweetsResponse($tweetResponses);
        }

        $userIds = array_values(array_unique(array_map(static fn ($tweet) => $tweet->userId(), $tweets)));
        $profiles = $this->profileRepository->findAllByUserIds($userIds);

        $authorNameById = [];
        foreach ($profiles as $profile) {
            $authorNameById[$profile->userId()] = $profile->name();
        }

        foreach ($tweets as $tweet) {
            $authorId = $tweet->userId();

            if (!isset($authorNameById[$authorId])) {
                $authorNameById[$authorId] = $this->profileRepository->getByUserId($authorId)->name();
            }

            $tweetResponses[] = new TweetResponse(
                id: $tweet->id(),
                content: $tweet->content(),
                createdAt: $tweet->createdAt(),
                updatedAt: $tweet->updatedAt(),
                authorId: $authorId,
                authorName: $authorNameById[$authorId],
                likesCount: $tweet->likes(),
            );
        }

        return new GetTweetsResponse($tweetResponses);
    }
}
