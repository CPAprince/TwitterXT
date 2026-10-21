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
    public function __construct(
        private readonly ModerationConfig $config,
        private ?ClientContract $client = null,
    ) {}

    private function getClient(): ClientContract
    {
        if (null !== $this->client) {
            return $this->client;
        }

        if (!$this->config->hasApiKey()) {
            throw new RuntimeException('OPENAI_API_KEY is not configured.');
        }

        $this->client = OpenAI::client($this->config->apiKey);

        return $this->client;
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

        $client = $this->getClient();

        $response = $client->moderations()->create([
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
                userId: $item->userId,
            );
        }

        return $decisions;
    }
}
