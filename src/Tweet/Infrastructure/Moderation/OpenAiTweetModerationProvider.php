<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Moderation;

use OpenAI;
use OpenAI\Contracts\ClientContract;
use OpenAI\Exceptions\RateLimitException;
use RuntimeException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
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

        $this->client = OpenAI::factory()
            ->withApiKey($this->config->apiKey)
            ->withHttpClient(new Psr18Client(HttpClient::create([
                'timeout' => $this->config->httpTimeout,
                'max_duration' => $this->config->httpMaxDuration,
            ])))
            ->make();

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

        try {
            $response = $client->moderations()->create([
                'model' => $this->config->model,
                'input' => $inputs,
            ]);
        } catch (RateLimitException $e) {
            $retryAfter = (int) ($e->response->getHeaderLine('Retry-After') ?: $e->response->getHeaderLine('retry-after') ?: '0');
            throw new RuntimeException($retryAfter > 0 ? sprintf('OpenAI rate limit exceeded (Retry-After: %d seconds).', $retryAfter) : 'OpenAI rate limit exceeded.', 429, $e);
        }

        $results = $response->results;

        if (count($results) !== count($items)) {
            throw new RuntimeException(sprintf('OpenAI moderation API returned %d result(s) for %d input(s).', count($results), count($items)));
        }

        $decisions = [];

        foreach ($items as $index => $item) {
            $result = $results[$index];

            $flagged = $result->flagged ?? false;
            /** @var list<string> $flaggedCategories */
            $flaggedCategories = [];

            foreach ($result->categories as $categoryName => $categoryData) {
                if ($categoryData->violated) {
                    $flaggedCategories[] = (string) $categoryName;
                }
            }

            $reason = null;
            if ($flagged) {
                $reason = [] !== $flaggedCategories
                    ? 'Flagged: '.implode(', ', $flaggedCategories)
                    : 'Rejected by OpenAI moderation.';
            }

            $decisions[] = new ModerationDecision(
                tweetId: $item->tweetId,
                approved: !$flagged,
                moderationVersion: $item->moderationVersion,
                reason: $reason,
                categories: $flaggedCategories,
                userId: $item->userId,
            );
        }

        return $decisions;
    }
}
