<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Domain\Tweet\Model;

use Assert\LazyAssertionException;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;

#[Group('unit')]
#[CoversMethod(Tweet::class, 'updateContent')]
final class TweetUpdateContentTest extends TestCase
{
    #[Test]
    public function updatesContentAndRefreshesUpdatedAt(): void
    {
        $tweet = Tweet::create(
            '123e4567-e89b-12d3-a456-426614174000',
            'Initial valid content',
        );

        $previousUpdatedAt = $tweet->updatedAt();

        usleep(1);

        $tweet->updateContent('Updated valid content');

        self::assertSame('Updated valid content', $tweet->content());
        self::assertGreaterThan($previousUpdatedAt, $tweet->updatedAt());
        self::assertInstanceOf(DateTimeImmutable::class, $tweet->updatedAt());
    }

    #[Test]
    #[DataProvider('invalidContentProvider')]
    public function throwsExceptionWhenContentIsNotValid(string $content): void
    {
        $tweet = Tweet::create(
            '123e4567-e89b-12d3-a456-426614174000',
            'Initial valid content',
        );

        $this->expectException(LazyAssertionException::class);

        $tweet->updateContent($content);
    }

    public static function invalidContentProvider(): iterable
    {
        yield 'empty content' => [''];
        yield 'whitespace only' => ['  '];
        yield 'too long content' => [str_repeat('a', 281)];
    }
}
