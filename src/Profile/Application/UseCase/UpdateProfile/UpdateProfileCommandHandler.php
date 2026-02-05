<?php

declare(strict_types=1);

namespace Twitter\Profile\Application\UseCase\UpdateProfile;

use Twitter\IAM\Domain\Auth\Exception\UnauthorizedException;
use Twitter\Profile\Domain\Profile\Exception\ProfileNotFoundException;
use Twitter\Profile\Domain\Profile\Model\ProfileRepository;

final readonly class UpdateProfileCommandHandler
{
    public function __construct(private ProfileRepository $profileRepository) {}

    /**
     * @throws ProfileNotFoundException
     * @throws UnauthorizedException
     */
    public function handle(UpdateProfileCommand $command): UpdateProfileCommandResult
    {
        $profile = $this->profileRepository->getByUserId($command->userId);

        // Note: userId in command should match authenticated user (validated in controller)
        // This is a safety check - if userId doesn't match profile userId, something is wrong
        if ($command->userId !== $profile->userId()) {
            throw new UnauthorizedException();
        }

        if (null !== $command->name) {
            $profile->updateName($command->name);
        }

        if (null !== $command->bio) {
            $profile->updateBio($command->bio);
        }

        $this->profileRepository->flush();

        return new UpdateProfileCommandResult(
            $profile->name(),
            $profile->bio(),
            $profile->updatedAt(),
        );
    }
}
