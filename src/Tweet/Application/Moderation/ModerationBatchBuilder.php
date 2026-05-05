<?php

declare(strict_types=1);

namespace Twitter\Tweet\Application\Moderation;

final readonly class ModerationBatchBuilder
{
    public function __construct(
        private ModerationConfig $config,
    ) {}

    /**
     * @param ModerationBatchItem[] $items
     */
    public function build(array $items): ?ModerationBatch
    {
        if ([] === $items) {
            return null;
        }

        $batchItems = [];
        $totalEstimatedTokens = 0;

        foreach ($items as $item) {
            if (count($batchItems) >= $this->config->batchMaxItems) {
                break;
            }

            $nextTotal = $totalEstimatedTokens + $item->estimatedTokens;

            if ($nextTotal > $this->config->batchHardTokenCap) {
                if ([] === $batchItems) {
                    $batchItems[] = $item;
                    $totalEstimatedTokens = $item->estimatedTokens;
                }

                break;
            }

            if ([] !== $batchItems && $nextTotal > $this->config->batchSoftTokenCap) {
                break;
            }

            $batchItems[] = $item;
            $totalEstimatedTokens = $nextTotal;
        }

        if ([] === $batchItems) {
            return null;
        }

        return new ModerationBatch(
            items: $batchItems,
            estimatedTokens: $totalEstimatedTokens,
        );
    }
}
