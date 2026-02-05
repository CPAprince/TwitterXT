<?php

declare(strict_types=1);

namespace Twitter\Like\UI\REST\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Twitter\IAM\Domain\User\Model\User;
use Twitter\Like\Application\UseCase\ToggleLike\ToggleLikeCommand;
use Twitter\Like\Application\UseCase\ToggleLike\ToggleLikeCommandHandler;
use Twitter\Like\Domain\Like\Exception\LikeAlreadyExistsException;

#[Route('api/tweets/{tweetId}/likes/toggle', name: 'api_tweets_like_toggle', methods: [Request::METHOD_POST])]
final class ToggleLikeController extends AbstractController
{
    public function __construct(
        private readonly ToggleLikeCommandHandler $handler,
    ) {}

    /**
     * @throws LikeAlreadyExistsException
     */
    public function __invoke(string $tweetId, #[CurrentUser] User $user): JsonResponse
    {
        $command = new ToggleLikeCommand($tweetId, $user->id());
        $result = $this->handler->handle($command);

        return $this->json([
            'liked' => $result->liked,
        ], Response::HTTP_OK);
    }
}
