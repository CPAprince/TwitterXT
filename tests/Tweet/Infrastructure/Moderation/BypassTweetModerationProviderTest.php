<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Infrastructure\Moderation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\Moderation\ModerationBatchItem;
use Twitter\Tweet\Infrastructure\Moderation\BypassTweetModerationProvider;

#[Group('unit')]
#[CoversClass(BypassTweetModerationProvider::class)]
final class BypassTweetModerationProviderTest extends TestCase
{
    private BypassTweetModerationProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new BypassTweetModerationProvider();
    }

    #[Test]
    public function approvesAllItems(): void
    {
        // Arrange
        $items = [
            new ModerationBatchItem('tweet-1', 'Hello world', 3, 0),
            new ModerationBatchItem('tweet-2', 'Some violent content', 5, 0),
        ];

        // Act
        $decisions = $this->provider->moderateBatch($items);

        // Assert
        self::assertCount(2, $decisions);

        foreach ($decisions as $decision) {
            self::assertTrue($decision->approved);
            self::assertNull($decision->reason);
        }
    }

    #[Test]
    public function preservesTweetIdsInDecisions(): void
    {
        // Arrange
        $items = [
            new ModerationBatchItem('tweet-abc', 'Text A', 2, 0),
            new ModerationBatchItem('tweet-xyz', 'Text B', 2, 0),
        ];

        // Act
        $decisions = $this->provider->moderateBatch($items);

        // Assert
        self::assertSame('tweet-abc', $decisions[0]->tweetId);
        self::assertSame('tweet-xyz', $decisions[1]->tweetId);
    }

    #[Test]
    public function returnsEmptyArrayForEmptyInput(): void
    {
        $decisions = $this->provider->moderateBatch([]);

        self::assertSame([], $decisions);
    }
}
