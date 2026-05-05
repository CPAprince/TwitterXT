<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

use InvalidArgumentException;

final readonly class ModerationBatch
{
    /**
     * @param ModerationBatchItem[] $items
     */
    public function __construct(
        public array $items,
        public int $estimatedTokens,
    ) {
        if ($this->estimatedTokens <= 0) {
            throw new InvalidArgumentException('estimatedTokens must be greater than 0.');
        }

        if ([] === $this->items) {
            throw new InvalidArgumentException('Batch items must not be empty.');
        }
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return string[]
     */
    public function tweetIds(): array
    {
        return array_map(
            static fn (ModerationBatchItem $item): string => $item->tweetId,
            $this->items
        );
    }
}
