<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\UseCase\GetUserTweets;

use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

final readonly class GetUserTweetsQueryHandler
{
    public function __construct(private TweetRepository $tweetRepository) {}

    public function handle(GetUserTweetsQuery $query): GetUserTweetsQueryResult
    {
        $tweets = $this->tweetRepository->getUserTweets($query->userId, $query->limit, $query->page);

        return new GetUserTweetsQueryResult($tweets);
    }
}
