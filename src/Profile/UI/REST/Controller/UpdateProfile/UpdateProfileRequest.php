<?php

declare(strict_types=1);

namespace Twitter\Profile\UI\REST\Controller\UpdateProfile;

final readonly class UpdateProfileRequest
{
    public function __construct(
        private ?string $name = null,
        private ?string $bio = null,
    ) {}

    public function name(): ?string
    {
        return $this->name;
    }

    public function bio(): ?string
    {
        return $this->bio;
    }
}
