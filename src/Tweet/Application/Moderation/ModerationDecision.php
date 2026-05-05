<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

final readonly class ModerationDecision
{
    public function __construct(
        public string $tweetId,
        public bool $approved,
        public int $moderationVersion,
        public ?string $reason = null,
        public array $categories = [],
    ) {}

    public function isRejected(): bool
    {
        return !$this->approved;
    }
}
