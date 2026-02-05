<?php

declare(strict_types=1);

namespace Twitter\Tweet\UI\REST\GetUserTweets;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twitter\Tweet\Application\UseCase\GetUserTweets\GetUserTweetsQuery;
use Twitter\Tweet\Application\UseCase\GetUserTweets\GetUserTweetsQueryHandler;
use Twitter\Tweet\Application\UseCase\Shared\TweetResponse;

#[Route('/api/profiles/{userId}/tweets', name: 'tweets_get_by_user', methods: [Request::METHOD_GET])]
final readonly class GetUserTweetsController
{
    public function __construct(private GetUserTweetsQueryHandler $queryHandler) {}

    public function __invoke(string $userId, Request $request): JsonResponse
    {
        $query = new GetUserTweetsQuery(
            $userId,
            (int) $request->query->get('limit', 0),
            (int) $request->query->get('page', 0),
        );

        $result = $this->queryHandler->handle($query);
        if (empty($result->tweets)) {
            return new JsonResponse([], Response::HTTP_NO_CONTENT);
        }

        $response = [];
        foreach ($result->tweets as $tweet) {
            $response[] = self::mapTweetResponse(new TweetResponse(
                $tweet->id(),
                $tweet->content(),
                $tweet->createdAt(),
                $tweet->updatedAt(),
                $tweet->userId(),
                '', // It is frontend responsibility to get the profile name
                $tweet->likes(),
            ));
        }

        return new JsonResponse($response, Response::HTTP_OK);
    }

    private static function mapTweetResponse(TweetResponse $tweetResponse): array
    {
        return [
            'id' => $tweetResponse->id,
            'content' => $tweetResponse->content,
            'createdAt' => $tweetResponse->createdAt->format(DATE_RFC3339),
            'updatedAt' => $tweetResponse->updatedAt->format(DATE_RFC3339),
            'author' => [
                'id' => $tweetResponse->authorId,
                'name' => $tweetResponse->authorName,
            ],
            'likesCount' => $tweetResponse->likesCount,
        ];
    }
}
