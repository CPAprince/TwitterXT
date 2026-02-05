<?php

declare(strict_types=1);

namespace Twitter\Profile\Application\UseCase\GetProfile;

use Twitter\Profile\Domain\Profile\Exception\ProfileNotFoundException;
use Twitter\Profile\Domain\Profile\Model\ProfileRepository;

final readonly class GetProfileQueryHandler
{
    public function __construct(private ProfileRepository $profileRepository) {}

    /**
     * @throws ProfileNotFoundException
     */
    public function handle(GetProfileQuery $query): GetProfileQueryResult
    {
        $profile = $this->profileRepository->getByUserId($query->userId);

        return new GetProfileQueryResult(
            $profile->name(),
            $profile->bio(),
            $profile->createdAt(),
            $profile->updatedAt(),
        );
    }
}
