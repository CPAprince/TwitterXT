<?php

declare(strict_types=1);

namespace Twitter\Profile\UI\REST\Controller\CreateProfile;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Twitter\Profile\Application\UseCase\CreateProfile\CreateProfileCommand;
use Twitter\Profile\Application\UseCase\CreateProfile\CreateProfileCommandHandler;

#[Route('/api/profiles', name: 'api_create_profile', methods: [Request::METHOD_POST])]
final readonly class CreateProfileController
{
    public function __construct(private CreateProfileCommandHandler $handler) {}

    public function __invoke(#[MapRequestPayload] CreateProfileRequest $request): Response
    {
        $command = new CreateProfileCommand($request->userId(), $request->name(), $request->bio());
        $this->handler->handle($command);

        return new Response(status: Response::HTTP_CREATED);
    }
}
