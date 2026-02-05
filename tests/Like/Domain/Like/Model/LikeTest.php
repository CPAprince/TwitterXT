<?php

declare(strict_types=1);

namespace Twitter\Tests\Like\Domain\Like\Model;

use Assert\LazyAssertionException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Like\Domain\Like\Model\Like;

#[Group('unit')]
#[CoversClass(Like::class)]
final class LikeTest extends TestCase
{
    #[Test]
    public function createSucceedsWithValidData(): void
    {
        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $userId = '550e8400-e29b-41d4-a716-446655440000';

        $like = Like::create($tweetId, $userId);

        self::assertSame($tweetId, $like->tweetId());
        self::assertSame($userId, $like->userId());
        self::assertInstanceOf(DateTimeImmutable::class, $like->createdAt());
    }

    #[Test]
    #[DataProvider('invalidUuidProvider')]
    public function createFailsWithInvalidTweetId(string $invalidTweetId): void
    {
        $this->expectException(LazyAssertionException::class);
        Like::create($invalidTweetId, '550e8400-e29b-41d4-a716-446655440000');
    }

    #[Test]
    #[DataProvider('invalidUuidProvider')]
    public function createFailsWithInvalidUserId(string $invalidUserId): void
    {
        $this->expectException(LazyAssertionException::class);
        Like::create('019b5f3f-d110-7908-9177-5df439942a8b', $invalidUserId);
    }

    public static function invalidUuidProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['  '];
        yield 'random string' => ['not-a-uuid'];
        yield 'too short' => ['123'];
    }
}
