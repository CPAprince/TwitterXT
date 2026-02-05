<?php

declare(strict_types=1);

namespace Twitter\Profile\Application\UseCase\GetProfile;

use DateTimeImmutable;

final readonly class GetProfileQueryResult
{
    public function __construct(
        public string $name,
        public string $bio,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}
}
