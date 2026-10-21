<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

interface ModerationWorkerLockInterface
{
    public function acquire(): bool;

    public function refresh(): bool;

    public function release(): void;
}
