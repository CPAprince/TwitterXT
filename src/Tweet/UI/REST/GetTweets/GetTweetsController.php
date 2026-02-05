<?php

declare(strict_types=1);

namespace Twitter\Tweet\UI\REST\GetTweets;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Twitter\Tweet\Application\UseCase\GetTweets\GetTweetsCommand;
use Twitter\Tweet\Application\UseCase\GetTweets\GetTweetsCommandHandler;
use Twitter\Tweet\Application\UseCase\Shared\TweetResponse;

#[Route('/api/tweets', name: 'tweets_get_all', methods: [Request::METHOD_GET])]
final readonly class GetTweetsController
{
    public function __construct(private GetTweetsCommandHandler $handler) {}

    public function __invoke(Request $request): JsonResponse
    {
        $command = new GetTweetsCommand(
            (int) $request->query->get('limit', 0),
            (int) $request->query->get('page', 0)
        );
        $dto = $this->handler->handle($command);

        $tweets = array_map(
            static fn (TweetResponse $tweetResponse): array => self::mapTweetResponse($tweetResponse),
            $dto->tweets
        );

        return new JsonResponse([
            'tweets' => $tweets,
        ], JsonResponse::HTTP_OK);
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
