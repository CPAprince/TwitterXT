<?php

declare(strict_types=1);

namespace Twitter\Profile\UI\REST\Controller\CreateProfile;

final readonly class CreateProfileRequest
{
    public function __construct(
        private string $userId,
        private string $name,
        private ?string $bio = null,
    ) {}

    public function userId(): string
    {
        return $this->userId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function bio(): ?string
    {
        return $this->bio;
    }
}
