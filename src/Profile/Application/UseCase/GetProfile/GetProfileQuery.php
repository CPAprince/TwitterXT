<?php

declare(strict_types=1);

namespace Twitter\Profile\Application\UseCase\GetProfile;

final readonly class GetProfileQuery
{
    public function __construct(public string $userId) {}
}
