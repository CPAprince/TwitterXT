<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

final readonly class ModerationRateLimitResult
{
    public function __construct(
        public bool $allowed,
        public ?string $reason = null,
    ) {}

    public static function allowed(): self
    {
        return new self(true, null);
    }

    public static function denied(string $reason): self
    {
        return new self(false, $reason);
    }
}
