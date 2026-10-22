<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

final readonly class ModerationFlushReport
{
    /**
     * @param list<string> $poisonTweetIds
     */
    public function __construct(
        public int $totalProcessed = 0,
        public int $approvedCount = 0,
        public int $rejectedCount = 0,
        public int $estimatedTokens = 0,
        public bool $rateLimitDenied = false,
        public ?string $rateLimitReason = null,
        public bool $isFullBatch = false,
        public bool $hasWork = false,
        public ?string $errorMessage = null,
        public array $poisonTweetIds = [],
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    public static function rateLimitDenied(string $reason, int $items, int $tokens): self
    {
        return new self(
            totalProcessed: $items,
            estimatedTokens: $tokens,
            rateLimitDenied: true,
            rateLimitReason: $reason,
            hasWork: true,
        );
    }

    public static function success(
        int $totalProcessed,
        int $approvedCount,
        int $rejectedCount,
        int $estimatedTokens,
        bool $isFullBatch,
    ): self {
        return new self(
            totalProcessed: $totalProcessed,
            approvedCount: $approvedCount,
            rejectedCount: $rejectedCount,
            estimatedTokens: $estimatedTokens,
            isFullBatch: $isFullBatch,
            hasWork: true,
        );
    }

    /**
     * @param list<string> $poisonTweetIds
     */
    public static function failure(string $errorMessage, array $poisonTweetIds = []): self
    {
        return new self(
            hasWork: true,
            errorMessage: $errorMessage,
            poisonTweetIds: $poisonTweetIds,
        );
    }
}
