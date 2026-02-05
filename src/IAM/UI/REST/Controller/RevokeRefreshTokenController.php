<?php

declare(strict_types=1);

namespace Twitter\IAM\UI\REST\Controller;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Twitter\IAM\Application\Logout\LogoutCommand;
use Twitter\IAM\Application\Logout\LogoutHandler;
use Twitter\IAM\Domain\Auth\Exception\UnauthorizedException;
use Twitter\IAM\UI\REST\Request\LogoutRequest;

final readonly class RevokeRefreshTokenController
{
    public function __construct(
        private Security $security,
        private LogoutHandler $logoutHandler,
    ) {}

    #[Route('/api/tokens', name: 'api_tokens_logout', methods: ['DELETE'])]
    public function logout(
        #[MapRequestPayload] LogoutRequest $logoutRequest,
    ): Response {
        $user = $this->security->getUser();

        if (!$user instanceof UserInterface) {
            throw new UnauthorizedException();
        }

        ($this->logoutHandler)(new LogoutCommand(
            username: $user->getUserIdentifier(), // email/username for bundle
            refreshToken: $logoutRequest->refreshToken(),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
