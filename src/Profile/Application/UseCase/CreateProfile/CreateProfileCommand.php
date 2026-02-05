<?php

declare(strict_types=1);

namespace Twitter\Profile\Application\UseCase\CreateProfile;

final readonly class CreateProfileCommand
{
    public function __construct(
        public string $userId,
        public string $name,
        public ?string $bio = null,
    ) {}
}
