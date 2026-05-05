<?php

declare(strict_types=1);

namespace Twitter\Tweet\UI\REST\CreateTweet;

use Assert\Assert;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Twitter\IAM\Domain\User\Model\User;
use Twitter\Shared\Infrastructure\RateLimiter\RateLimited;
use Twitter\Tweet\Application\UseCase\CreateTweet\CreateTweetCommand;
use Twitter\Tweet\Application\UseCase\CreateTweet\CreateTweetCommandHandlerInterface;

#[Route('/api/tweets', name: 'api_create_tweet', methods: [Request::METHOD_POST])]
#[RateLimited('tweet_create')]
final readonly class CreateTweetController
{
    public function __construct(private CreateTweetCommandHandlerInterface $handler) {}

    public function __invoke(
        #[MapRequestPayload] CreateTweetRequest $request,
        #[CurrentUser] User $authUser,
    ): JsonResponse {
        $userId = $authUser->id();

        Assert::that($userId)->uuid();

        $command = new CreateTweetCommand($userId, $request->content());
        $result = $this->handler->handle($command);

        return new JsonResponse([
            'tweetId' => $result->tweetId,
            'createdAt' => $result->createdAt->format(DATE_RFC3339),
            'updatedAt' => $result->updatedAt->format(DATE_RFC3339),
        ], Response::HTTP_CREATED);
    }
}
