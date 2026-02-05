<?php

declare(strict_types=1);

namespace Twitter\Profile\UI\REST\Controller\GetProfile;

use Assert\Assert;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Twitter\Profile\Application\UseCase\GetProfile\GetProfileQuery;
use Twitter\Profile\Application\UseCase\GetProfile\GetProfileQueryHandler;
use Twitter\Profile\Domain\Profile\Exception\ProfileNotFoundException;

#[Route('/api/profiles/{userId}', name: 'api_get_profile', methods: [Request::METHOD_GET])]
final readonly class GetProfileController
{
    public function __construct(private GetProfileQueryHandler $queryHandler) {}

    /**
     * @throws ProfileNotFoundException
     */
    public function __invoke(string $userId): JsonResponse
    {
        Assert::that($userId)->uuid();

        $query = new GetProfileQuery($userId);
        $result = $this->queryHandler->handle($query);

        return new JsonResponse([
            'userId' => $userId,
            'name' => $result->name,
            'bio' => $result->bio,
            'createdAt' => $result->createdAt->format(DATE_RFC3339),
            'updatedAt' => $result->updatedAt->format(DATE_RFC3339),
        ]);
    }
}
