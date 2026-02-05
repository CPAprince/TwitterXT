<?php

declare(strict_types=1);

namespace Twitter\Registration\UI\REST\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Twitter\Registration\Application\UseCase\RegisterUser\RegisterUserCommand;
use Twitter\Registration\Application\UseCase\RegisterUser\RegisterUserCommandHandler;

#[Route('/api/registration', name: 'api_registration', methods: ['POST'])]
final readonly class RegisterUserController
{
    public function __construct(
        private RegisterUserCommandHandler $registerUserCommandHandler,
    ) {}

    public function __invoke(
        #[MapRequestPayload]
        RegisterUserRequest $request,
    ): JsonResponse {
        $result = $this->registerUserCommandHandler->handle(
            new RegisterUserCommand(
                email: $request->email(),
                password: $request->password(),
                name: $request->name(),
                bio: $request->bio(),
            ),
        );

        return new JsonResponse(['id' => $result->userId], Response::HTTP_CREATED);
    }
}
