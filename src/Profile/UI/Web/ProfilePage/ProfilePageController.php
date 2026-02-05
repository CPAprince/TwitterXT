<?php

declare(strict_types=1);

namespace Twitter\Profile\UI\Web\ProfilePage;

use Assert\Assert;
use Assert\AssertionFailedException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Twitter\Profile\Application\UseCase\GetProfile\GetProfileQuery;
use Twitter\Profile\Application\UseCase\GetProfile\GetProfileQueryHandler;
use Twitter\Profile\Domain\Profile\Exception\ProfileNotFoundException;

#[Route('/p/{userId}', name: 'profile_page', methods: ['GET'])]
final class ProfilePageController extends AbstractController
{
    public function __construct(
        private readonly GetProfileQueryHandler $profileQueryHandler,
    ) {}

    /**
     * @throws ProfileNotFoundException
     */
    public function __invoke(string $userId): Response
    {
        try {
            Assert::that($userId)->uuid();
        } catch (AssertionFailedException) {
            throw new NotFoundHttpException('Profile not found.');
        }

        try {
            $profileResult = $this->profileQueryHandler->handle(new GetProfileQuery($userId));
        } catch (ProfileNotFoundException) {
            throw new NotFoundHttpException('Profile not found.');
        }

        return $this->render('page/profile.html.twig', [
            'profile' => [
                'userId' => $userId,
                'name' => $profileResult->name,
                'bio' => $profileResult->bio,
                'createdAt' => $profileResult->createdAt,
                'updatedAt' => $profileResult->updatedAt,
            ],
        ]);
    }
}
