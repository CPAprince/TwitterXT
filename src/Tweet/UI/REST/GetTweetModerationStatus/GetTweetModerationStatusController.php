<?php

declare(strict_types=1);

namespace Twitter\Tweet\UI\REST\GetTweetModerationStatus;

use Assert\Assert;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Twitter\IAM\Domain\User\Model\User;
use Twitter\Shared\Infrastructure\RateLimiter\RateLimited;
use Twitter\Tweet\Application\UseCase\GetTweetModerationStatus\GetTweetModerationStatusCommand;
use Twitter\Tweet\Application\UseCase\GetTweetModerationStatus\GetTweetModerationStatusCommandHandlerInterface;
use Twitter\Tweet\Domain\Tweet\Exception\TweetAccessDeniedException;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;

#[Route('/api/tweets/{tweetId}/moderation-status', name: 'api_tweet_moderation_status', methods: ['GET'])]
#[RateLimited('moderation_status_poll')]
final readonly class GetTweetModerationStatusController
{
    public function __construct(
        private GetTweetModerationStatusCommandHandlerInterface $handler,
    ) {}

    /**
     * @throws TweetNotFoundException
     * @throws TweetAccessDeniedException
     */
    public function __invoke(
        string $tweetId,
        #[CurrentUser] User $authUser,
    ): JsonResponse {
        Assert::that($tweetId)->uuid();

        $response = $this->handler->handle(
            new GetTweetModerationStatusCommand($tweetId, $authUser->id())
        );

        $jsonResponse = new JsonResponse([
            'tweetId' => $response->tweetId,
            'status' => $response->status,
        ], Response::HTTP_OK);

        $jsonResponse->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');

        return $jsonResponse;
    }
}
