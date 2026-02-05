<?php

declare(strict_types=1);

namespace Twitter\Tweet\UI\REST\UpdateTweet;

use Assert\Assert;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Twitter\IAM\Domain\User\Model\User;
use Twitter\Tweet\Application\UseCase\UpdateTweet\UpdateTweetCommand;
use Twitter\Tweet\Application\UseCase\UpdateTweet\UpdateTweetCommandHandler;
use Twitter\Tweet\Domain\Tweet\Exception\TweetAccessDeniedException;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;

#[Route('/api/tweets/{tweetId}', name: 'api_update_tweet', methods: [Request::METHOD_PATCH])]
final readonly class UpdateTweetController
{
    public function __construct(private UpdateTweetCommandHandler $commandHandler) {}

    /**
     * @throws TweetAccessDeniedException
     * @throws TweetNotFoundException
     */
    public function __invoke(
        string $tweetId,
        #[MapRequestPayload] UpdateTweetRequest $request,
        #[CurrentUser] User $authUser,
    ): JsonResponse {
        Assert::that($tweetId)->uuid();

        $command = new UpdateTweetCommand($authUser->id(), $tweetId, $request->content());
        $result = $this->commandHandler->handle($command);

        return new JsonResponse([
            'content' => $result->content,
            'updatedAt' => $result->updatedAt->format(DATE_RFC3339),
        ], JsonResponse::HTTP_OK);
    }
}
