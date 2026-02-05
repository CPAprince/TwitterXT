<?php

declare(strict_types=1);

namespace Twitter\IAM\Application\Logout;

final class LogoutHandler
{
    public function __construct(private readonly RefreshTokenRepository $repository) {}

    public function __invoke(LogoutCommand $command): void
    {
        $this->repository->revoke(
            $command->refreshToken(),
            $command->username(),
        );
    }
}
