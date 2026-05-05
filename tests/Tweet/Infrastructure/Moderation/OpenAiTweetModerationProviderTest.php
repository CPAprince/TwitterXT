<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Infrastructure\Moderation;

use OpenAI\Responses\Moderations\CreateResponse;
use OpenAI\Testing\ClientFake;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use RuntimeException;
use Twitter\Tweet\Application\Moderation\ModerationBatchItem;
use Twitter\Tweet\Application\Moderation\ModerationConfig;
use Twitter\Tweet\Application\Moderation\ModerationMode;
use Twitter\Tweet\Infrastructure\Moderation\OpenAiTweetModerationProvider;

#[Group('unit')]
#[CoversClass(OpenAiTweetModerationProvider::class)]
final class OpenAiTweetModerationProviderTest extends TestCase
{
    // ── constructor ───────────────────────────────────────────────────────────

    #[Test]
    public function throwsRuntimeExceptionWhenApiKeyIsNotConfigured(): void
    {
        $config = $this->makeConfig(apiKey: null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/OPENAI_API_KEY is not configured/');

        new OpenAiTweetModerationProvider($config);
    }

    // ── moderateBatch ─────────────────────────────────────────────────────────

    #[Test]
    public function returnsApprovedDecisionWhenContentIsNotFlagged(): void
    {
        // Arrange
        $provider = $this->makeProviderWithFakeClient(flagged: false);
        $items = [new ModerationBatchItem('tweet-1', 'Hello world', 3, 1)];

        // Act
        $decisions = $provider->moderateBatch($items);

        // Assert
        self::assertCount(1, $decisions);
        self::assertSame('tweet-1', $decisions[0]->tweetId);
        self::assertTrue($decisions[0]->approved);
        self::assertNull($decisions[0]->reason);
    }

    #[Test]
    public function returnsRejectedDecisionWhenContentIsFlagged(): void
    {
        // Arrange
        $provider = $this->makeProviderWithFakeClient(flagged: true);
        $items = [new ModerationBatchItem('tweet-2', 'Harmful content', 4, 1)];

        // Act
        $decisions = $provider->moderateBatch($items);

        // Assert
        self::assertCount(1, $decisions);
        self::assertSame('tweet-2', $decisions[0]->tweetId);
        self::assertFalse($decisions[0]->approved);
        self::assertNotNull($decisions[0]->reason);
    }

    #[Test]
    public function returnsEmptyArrayForEmptyInput(): void
    {
        // The client should never be called when input is empty
        $provider = $this->makeProviderWithFakeClient(flagged: false);
        $decisions = $provider->moderateBatch([]);

        self::assertSame([], $decisions);
    }

    #[Test]
    public function throwsRuntimeExceptionWhenApiReturnsFewerResultsThanInputs(): void
    {
        // Arrange – two items but API returns only one result (Bug 2 guard)
        $provider = $this->makeProviderWithFakeClient(flagged: false, responseCount: 1);
        $items = [
            new ModerationBatchItem('tweet-1', 'Text A', 3, 1),
            new ModerationBatchItem('tweet-2', 'Text B', 3, 1),
        ];

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/returned 1 result\(s\) for 2 input\(s\)/');

        $provider->moderateBatch($items);
    }

    #[Test]
    public function preservesTweetIdOrderFromInputItems(): void
    {
        // Arrange – three items; each result maps by index
        $provider = $this->makeProviderWithFakeClient(flagged: false, itemCount: 3);
        $items = [
            new ModerationBatchItem('t-first', 'A', 2, 1),
            new ModerationBatchItem('t-second', 'B', 2, 1),
            new ModerationBatchItem('t-third', 'C', 2, 1),
        ];

        // Act
        $decisions = $provider->moderateBatch($items);

        // Assert
        self::assertSame('t-first', $decisions[0]->tweetId);
        self::assertSame('t-second', $decisions[1]->tweetId);
        self::assertSame('t-third', $decisions[2]->tweetId);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * Creates a provider with a fake API client injected via reflection.
     * The fake client returns `$responseCount` results, each with `flagged = $flagged`.
     * When $responseCount is null it defaults to $itemCount (matching response).
     *
     * Uses OpenAI\Testing\ClientFake so the property type (ClientContract) is satisfied.
     */
    private function makeProviderWithFakeClient(
        bool $flagged,
        int $itemCount = 1,
        ?int $responseCount = null,
    ): OpenAiTweetModerationProvider {
        $responseCount ??= $itemCount;

        // Build minimal result attributes – categories/scores empty, only flagged matters
        $resultAttributes = array_fill(
            0,
            $responseCount,
            ['categories' => [], 'category_scores' => [], 'flagged' => $flagged]
        );

        $response = CreateResponse::from(
            ['id' => 'modr-test', 'model' => 'omni-moderation-latest', 'results' => $resultAttributes],
            CreateResponse::fakeResponseMetaInformation(),
        );

        // ClientFake implements ClientContract and queues pre-built responses
        $clientFake = new ClientFake([$response]);

        // Construct with a real (fake) API key so the constructor guard passes
        $provider = new OpenAiTweetModerationProvider($this->makeConfig(apiKey: 'sk-fake-key'));

        // Inject via reflection — property type is ClientContract, ClientFake satisfies it
        (new ReflectionProperty(OpenAiTweetModerationProvider::class, 'client'))
            ->setValue($provider, $clientFake);

        return $provider;
    }

    private function makeConfig(?string $apiKey = 'sk-test'): ModerationConfig
    {
        return new ModerationConfig(
            enabled: true,
            mode: ModerationMode::LIVE,
            provider: 'openai',
            model: 'omni-moderation-latest',
            apiKey: $apiKey,
            batchMaxItems: 10,
            batchSoftTokenCap: 1_000,
            batchHardTokenCap: 2_000,
            batchFlushIntervalMs: 500,
            limitRpm: 60,
            limitTpm: 100_000,
            limitRpd: 1_000,
            demoApprovePercent: 80,
            redisQueueKey: 'moderation:queue',
            redisFlushLockKey: 'moderation:lock',
            redisRateReqPrefix: 'moderation:rate:req:',
            redisRateTokPrefix: 'moderation:rate:tok:',
            redisRateReqDayPrefix: 'moderation:rate:day:',
            workerIdleSleepMs: 1_000,
            workerLockTtlSeconds: 60,
            workerMaxFetchItems: 100,
        );
    }
}
