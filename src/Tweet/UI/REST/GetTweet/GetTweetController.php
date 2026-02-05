<?php

declare(strict_types=1);

namespace Twitter\Tweet\UI\REST\GetTweet;

use Assert\Assert;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Twitter\Tweet\Application\UseCase\GetTweet\GetTweetCommand;
use Twitter\Tweet\Application\UseCase\GetTweet\GetTweetCommandHandler;
use Twitter\Tweet\Application\UseCase\Shared\TweetResponse;

#[Route('/api/tweets/{tweetId}', name: 'tweets_get', methods: ['GET'])]
final readonly class GetTweetController
{
    public function __construct(private GetTweetCommandHandler $handler) {}

    public function __invoke(string $tweetId): JsonResponse
    {
        Assert::lazy()->tryAll()
            ->that($tweetId, 'tweetId')->notBlank()->uuid()
            ->verifyNow();

        $tweetResponse = $this->handler->handle(new GetTweetCommand($tweetId));

        return new JsonResponse(
            self::mapTweetResponse($tweetResponse),
            JsonResponse::HTTP_OK
        );
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
