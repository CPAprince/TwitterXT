<?php

declare(strict_types=1);

namespace Twitter\Profile\Application\UseCase\CreateProfile;

use Twitter\Profile\Domain\Profile\Model\Profile;
use Twitter\Profile\Domain\Profile\Model\ProfileRepository;

final readonly class CreateProfileCommandHandler
{
    public function __construct(private ProfileRepository $profileRepository) {}

    public function handle(CreateProfileCommand $command): void
    {
        $profile = Profile::create($command->userId, $command->name, $command->bio);
        $this->profileRepository->add($profile);
    }
}
