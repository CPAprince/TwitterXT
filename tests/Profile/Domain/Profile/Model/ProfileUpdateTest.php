<?php

declare(strict_types=1);

namespace Twitter\Tests\Profile\Domain\Profile\Model;

use Assert\LazyAssertionException;
use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Profile\Domain\Profile\Model\Profile;

#[Group('unit')]
#[CoversMethod(Profile::class, 'updateName')]
#[CoversMethod(Profile::class, 'updateBio')]
final class ProfileUpdateTest extends TestCase
{
    #[Test]
    public function updateNameUpdatesNameAndRefreshesUpdatedAt(): void
    {
        $profile = Profile::create(
            '019b5f3f-d110-7908-9177-5df439942a8b',
            'John Doe',
            'Bio'
        );

        $previousUpdatedAt = $profile->updatedAt();

        usleep(1);

        $profile->updateName('Jane Doe');

        self::assertSame('Jane Doe', $profile->name());
        self::assertGreaterThan($previousUpdatedAt, $profile->updatedAt());
        self::assertInstanceOf(DateTimeImmutable::class, $profile->updatedAt());
    }

    #[Test]
    #[DataProvider('invalidNameProvider')]
    public function updateNameThrowsExceptionWhenNameIsNotValid(string $name): void
    {
        $profile = Profile::create(
            '019b5f3f-d110-7908-9177-5df439942a8b',
            'John Doe',
            'Bio'
        );

        $this->expectException(LazyAssertionException::class);

        $profile->updateName($name);
    }

    #[Test]
    public function updateBioUpdatesBioAndRefreshesUpdatedAt(): void
    {
        $profile = Profile::create(
            '019b5f3f-d110-7908-9177-5df439942a8b',
            'John Doe',
            'Old bio'
        );

        $previousUpdatedAt = $profile->updatedAt();

        usleep(1);

        $profile->updateBio('New bio');

        self::assertSame('New bio', $profile->bio());
        self::assertGreaterThan($previousUpdatedAt, $profile->updatedAt());
        self::assertInstanceOf(DateTimeImmutable::class, $profile->updatedAt());
    }

    #[Test]
    public function updateBioConvertsNullToEmptyString(): void
    {
        $profile = Profile::create(
            '019b5f3f-d110-7908-9177-5df439942a8b',
            'John Doe',
            'Old bio'
        );

        $previousUpdatedAt = $profile->updatedAt();

        usleep(1);

        $profile->updateBio(null);

        self::assertSame('', $profile->bio());
        self::assertGreaterThan($previousUpdatedAt, $profile->updatedAt());
        self::assertInstanceOf(DateTimeImmutable::class, $profile->updatedAt());
    }

    #[Test]
    #[DataProvider('invalidBioProvider')]
    public function updateBioThrowsExceptionWhenBioIsNotValid(string $bio): void
    {
        $profile = Profile::create(
            '019b5f3f-d110-7908-9177-5df439942a8b',
            'John Doe',
            'Bio'
        );

        $this->expectException(LazyAssertionException::class);

        $profile->updateBio($bio);
    }

    public static function invalidNameProvider(): Generator
    {
        yield 'empty value' => [''];
        yield 'whitespace only' => ['  '];
        yield 'less than 3 characters long' => ['Jo'];
        yield 'more than 50 characters long' => [str_repeat('a', 51)];
    }

    public static function invalidBioProvider(): Generator
    {
        yield 'more than 300 characters long' => [str_repeat('b', 301)];
    }
}
