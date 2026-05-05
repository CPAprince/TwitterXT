<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Moderation;

use OpenAI;
use OpenAI\Contracts\ClientContract;
use RuntimeException;
use Twitter\Tweet\Application\Moderation\ModerationBatchItem;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationDecision;
use Twitter\Tweet\Application\Moderation\TweetModerationProviderInterface;

final class OpenAiTweetModerationProvider implements TweetModerationProviderInterface
{
    private ClientContract $client;

    public function __construct(
        private readonly ModerationConfig $config,
    ) {
        if (!$this->config->hasApiKey()) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $this->client = OpenAI::client($this->config->apiKey);
    }

    public function moderateBatch(array $items): array
    {
        if ([] === $items) {
            return [];
        }

        $inputs = array_map(
            static fn (ModerationBatchItem $item) => $item->text,
            $items
        );

        $response = $this->client->moderations()->create([
            'model' => $this->config->model,
            'input' => $inputs,
        ]);

        $results = $response->results;

        if (count($results) !== count($items)) {
            throw new RuntimeException(sprintf('OpenAI moderation API returned %d result(s) for %d input(s).', count($results), count($items)));
        }

        $decisions = [];

        foreach ($items as $index => $item) {
            $result = $results[$index];

            $flagged = $result->flagged ?? false;

            $decisions[] = new ModerationDecision(
                tweetId: $item->tweetId,
                approved: !$flagged,
                moderationVersion: $item->moderationVersion,
                reason: $flagged ? 'Rejected by OpenAI moderation.' : null,
                categories: [],
            );
        }

        return $decisions;
    }
}
