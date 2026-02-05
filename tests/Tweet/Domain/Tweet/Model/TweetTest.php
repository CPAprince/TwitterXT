<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Domain\Tweet\Model;

use Assert\LazyAssertionException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;

#[Group('unit')]
#[CoversClass(Tweet::class)]
final class TweetTest extends TestCase
{
    #[Test]
    public function createSucceedsWithValidData(): void
    {
        // Arrange
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $content = 'Hello world';

        // Act
        $tweet = Tweet::create($userId, $content);

        // Assert
        self::assertSame($userId, $tweet->userId());
        self::assertSame($content, $tweet->content());
        self::assertNotEmpty($tweet->id());
        self::assertInstanceOf(DateTimeImmutable::class, $tweet->createdAt());
        self::assertInstanceOf(DateTimeImmutable::class, $tweet->updatedAt());
        self::assertSame(0, $tweet->likes());
    }

    #[Test]
    #[DataProvider('invalidUserIdProvider')]
    public function createFailsWithInvalidUserId(string $userId): void
    {
        // Arrange
        $this->expectException(LazyAssertionException::class);

        // Act
        Tweet::create($userId, 'Valid content');

        // Assert (exception)
    }

    public static function invalidUserIdProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['  '];
        yield 'random string' => ['abc123?'];
    }

    #[Test]
    #[DataProvider('invalidContentProvider')]
    public function createFailsWithInvalidContent(string $content): void
    {
        // Arrange
        $this->expectException(LazyAssertionException::class);

        // Act
        Tweet::create(
            '550e8400-e29b-41d4-a716-446655440000',
            $content
        );

        // Assert (exception)
    }

    public static function invalidContentProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['  '];
        yield 'too long content' => [str_repeat('a', 281)];
    }

    #[Test]
    public function likeIncreasesLikesCount(): void
    {
        // Arrange
        $tweet = Tweet::create(
            '550e8400-e29b-41d4-a716-446655440000',
            'Hello world',
        );

        // Act
        $tweet->like();

        // Assert
        self::assertSame(1, $tweet->likes());
    }

    #[Test]
    public function dislikeDecreasesLikesCount(): void
    {
        // Arrange
        $tweet = Tweet::create(
            '550e8400-e29b-41d4-a716-446655440000',
            'Hello world',
        );
        $tweet->like();
        $tweet->like();

        // Act
        $tweet->dislike();

        // Assert
        self::assertSame(1, $tweet->likes());
    }

    #[Test]
    public function dislikeDoesNotGoBelowZero(): void
    {
        // Arrange
        $tweet = Tweet::create(
            '550e8400-e29b-41d4-a716-446655440000',
            'Hello world',
        );

        // Act
        $tweet->dislike();

        // Assert
        self::assertSame(0, $tweet->likes());
    }
}
