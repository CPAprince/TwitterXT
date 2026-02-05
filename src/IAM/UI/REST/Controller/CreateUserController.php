<?php

declare(strict_types=1);

namespace Twitter\IAM\UI\REST\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Twitter\IAM\Application\CreateUser\CreateUserCommand;
use Twitter\IAM\Application\CreateUser\CreateUserCommandHandler;
use Twitter\IAM\Domain\User\Exception\InvalidEmailException;
use Twitter\IAM\Domain\User\Exception\InvalidPasswordException;

#[Route('/api/users', name: 'api_create_user', methods: ['POST'])]
final readonly class CreateUserController
{
    public function __construct(
        private CreateUserCommandHandler $createUserCommandHandler,
    ) {}

    /**
     * @throws InvalidEmailException
     * @throws InvalidPasswordException
     */
    public function __invoke(
        #[MapRequestPayload]
        CreateUserRequest $request,
    ): JsonResponse {
        $command = new CreateUserCommand($request->email(), $request->password());
        $result = $this->createUserCommandHandler->handle($command);

        return new JsonResponse(['id' => $result->userId], Response::HTTP_CREATED);
    }
}
