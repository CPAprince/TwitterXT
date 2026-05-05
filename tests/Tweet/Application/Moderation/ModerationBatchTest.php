<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Application\Moderation;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\Moderation\ModerationBatch;
use Twitter\Tweet\Application\Moderation\ModerationBatchItem;

#[Group('unit')]
#[CoversClass(ModerationBatch::class)]
final class ModerationBatchTest extends TestCase
{
    #[Test]
    public function throwsWhenItemsAreEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ModerationBatch([], 10);
    }

    #[Test]
    public function throwsWhenEstimatedTokensIsZero(): void
    {
        $item = new ModerationBatchItem('tweet-1', 'Hello', 5, 0);

        $this->expectException(InvalidArgumentException::class);

        new ModerationBatch([$item], 0);
    }

    #[Test]
    public function throwsWhenEstimatedTokensIsNegative(): void
    {
        $item = new ModerationBatchItem('tweet-1', 'Hello', 5, 0);

        $this->expectException(InvalidArgumentException::class);

        new ModerationBatch([$item], -1);
    }

    #[Test]
    public function countReturnsNumberOfItems(): void
    {
        // Arrange
        $items = [
            new ModerationBatchItem('tweet-1', 'Hello', 3, 0),
            new ModerationBatchItem('tweet-2', 'World', 4, 0),
        ];

        // Act
        $batch = new ModerationBatch($items, 7);

        // Assert
        self::assertSame(2, $batch->count());
    }

    #[Test]
    public function tweetIdsExtractsAllIds(): void
    {
        // Arrange
        $items = [
            new ModerationBatchItem('tweet-abc', 'Hello', 3, 0),
            new ModerationBatchItem('tweet-xyz', 'World', 4, 0),
        ];

        $batch = new ModerationBatch($items, 7);

        // Act & Assert
        self::assertSame(['tweet-abc', 'tweet-xyz'], $batch->tweetIds());
    }
}
