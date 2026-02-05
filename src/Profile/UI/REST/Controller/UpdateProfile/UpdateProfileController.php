<?php

declare(strict_types=1);

namespace Twitter\Profile\UI\REST\Controller\UpdateProfile;

use Assert\Assert;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Twitter\IAM\Domain\Auth\Exception\UnauthorizedException;
use Twitter\IAM\Domain\User\Model\User;
use Twitter\Profile\Application\UseCase\UpdateProfile\UpdateProfileCommand;
use Twitter\Profile\Application\UseCase\UpdateProfile\UpdateProfileCommandHandler;
use Twitter\Profile\Domain\Profile\Exception\ProfileNotFoundException;

#[Route('/api/profiles/{userId}', name: 'api_update_profile', methods: [Request::METHOD_PATCH])]
final readonly class UpdateProfileController
{
    public function __construct(private UpdateProfileCommandHandler $handler) {}

    /**
     * @throws ProfileNotFoundException
     * @throws UnauthorizedException
     */
    public function __invoke(
        string $userId,
        #[MapRequestPayload] UpdateProfileRequest $request,
        #[CurrentUser] User $authUser,
    ): JsonResponse {
        Assert::that($userId)->uuid();

        // Validate that the authenticated user is updating their own profile
        if ($authUser->id() !== $userId) {
            throw new UnauthorizedException();
        }

        // At least one field must be provided
        if (null === $request->name() && null === $request->bio()) {
            return new JsonResponse(
                ['error' => ['code' => 'VALIDATION_ERROR', 'message' => 'At least one field (name or bio) must be provided']],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $command = new UpdateProfileCommand(
            $userId,
            $request->name(),
            $request->bio(),
        );

        $result = $this->handler->handle($command);

        return new JsonResponse([
            'userId' => $userId,
            'name' => $result->name,
            'bio' => $result->bio,
            'updatedAt' => $result->updatedAt->format(DATE_RFC3339),
        ], JsonResponse::HTTP_OK);
    }
}
