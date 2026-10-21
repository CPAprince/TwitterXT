<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Infrastructure\Moderation;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Infrastructure\Moderation\MySqlModerationTweetSource;

#[Group('unit')]
#[CoversClass(MySqlModerationTweetSource::class)]
final class MySqlModerationTweetSourceTest extends TestCase
{
    private Connection&MockObject $connection;
    private MySqlModerationTweetSource $source;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->source = new MySqlModerationTweetSource($this->connection);
    }

    #[Test]
    public function findByIdsReturnsEmptyArrayForEmptyInput(): void
    {
        $this->connection->expects(self::never())->method('fetchAllAssociative');

        self::assertSame([], $this->source->findByIds([]));
    }

    #[Test]
    public function findByIdsReturnsCandidateForEachRow(): void
    {
        // Arrange
        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $this->connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => $tweetId, 'content' => 'Hello world', 'moderation_version' => 1],
            ]);

        // Act
        $candidates = $this->source->findByIds([$tweetId]);

        // Assert
        self::assertCount(1, $candidates);
        self::assertSame($tweetId, $candidates[0]->tweetId);
        self::assertSame('Hello world', $candidates[0]->text);
        self::assertSame(1, $candidates[0]->moderationVersion);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function findByIdsReturnsEmptyWhenAllIdsAreStale(): void
    {
        // DB returns no rows (tweets already deleted or wrong status)
        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $result = $this->source->findByIds([
            '019b5f3f-d110-7908-9177-5df439942a8b',
            '019b5f41-0e5b-7f65-8b7a-0f9c0b3b3c11',
        ]);

        self::assertSame([], $result);
    }

    #[Test]
    public function findByIdsQueriesOnlyPendingTweets(): void
    {
        // The SQL must filter by moderation_status = PENDING
        $this->connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::stringContains('moderation_status = ?'),
            )
            ->willReturn([]);

        $this->source->findByIds(['019b5f3f-d110-7908-9177-5df439942a8b']);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function findByIdsPreservesRequestedIdOrder(): void
    {
        // Arrange – request IDs in order [t1, t2, t3], DB returns them reversed
        $id1 = '019b5f3f-d110-7908-9177-5df439942a8b';
        $id2 = '019b5f41-0e5b-7f65-8b7a-0f9c0b3b3c11';
        $id3 = '550e8400-e29b-41d4-a716-446655440000';

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => $id3, 'content' => 'C', 'moderation_version' => 1],
                ['id' => $id1, 'content' => 'A', 'moderation_version' => 1],
                ['id' => $id2, 'content' => 'B', 'moderation_version' => 2],
            ]);

        // Act
        $candidates = $this->source->findByIds([$id1, $id2, $id3]);

        // Assert – output must follow requested order, not DB order
        self::assertSame($id1, $candidates[0]->tweetId);
        self::assertSame($id2, $candidates[1]->tweetId);
        self::assertSame($id3, $candidates[2]->tweetId);
    }

    #[AllowMockObjectsWithoutExpectations]
    #[Test]
    public function findByIdsModerationVersionIsPreservedPerRow(): void
    {
        $id1 = '019b5f3f-d110-7908-9177-5df439942a8b';
        $id2 = '019b5f41-0e5b-7f65-8b7a-0f9c0b3b3c11';

        $this->connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => $id1, 'content' => 'A', 'moderation_version' => 1],
                ['id' => $id2, 'content' => 'B', 'moderation_version' => 3],
            ]);

        $candidates = $this->source->findByIds([$id1, $id2]);

        self::assertSame(1, $candidates[0]->moderationVersion);
        self::assertSame(3, $candidates[1]->moderationVersion);
    }

    #[Test]
    public function findByIdsSelectsModerationVersionColumn(): void
    {
        // The SELECT must include moderation_version so it is available for version-guard
        $this->connection
            ->expects(self::once())
            ->method('fetchAllAssociative')
            ->with(
                self::stringContains('moderation_version'),
            )
            ->willReturn([]);

        $this->source->findByIds(['019b5f3f-d110-7908-9177-5df439942a8b']);
    }
}
