<?php

declare(strict_types=1);

namespace Twitter\Tests\Profile\Domain\Profile\Model;

use Assert\LazyAssertionException;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Profile\Domain\Profile\Model\Profile;

#[Group('unit')]
#[CoversClass(Profile::class)]
final class ProfileTest extends TestCase
{
    #[Test]
    public function createsProfileWithValidData(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $name = 'John Doe';
        $bio = 'This is a sample bio';

        $profile = Profile::create($userId, $name, $bio);

        $this->assertSame($userId, $profile->userId());
        $this->assertSame($name, $profile->name());
        $this->assertSame($bio, $profile->bio());
    }

    #[Test]
    public function createsProfileWithoutBio(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $name = 'Jane Doe';

        $profile = Profile::create($userId, $name);

        $this->assertSame('', $profile->bio());
    }

    #[Test]
    #[DataProvider('invalidUserIdProvider')]
    public function rejectsInvalidUserId(string $userId): void
    {
        $this->expectException(LazyAssertionException::class);

        Profile::create($userId, 'John Doe');
    }

    #[Test]
    #[DataProvider('invalidNameProvider')]
    public function rejectsInvalidName(string $name): void
    {
        $this->expectException(LazyAssertionException::class);

        Profile::create('019b5f3f-d110-7908-9177-5df439942a8b', $name);
    }

    #[Test]
    #[DataProvider('invalidBioProvider')]
    public function rejectsInvalidBio(string $bio): void
    {
        $this->expectException(LazyAssertionException::class);

        Profile::create('019b5f3f-d110-7908-9177-5df439942a8b', 'John Doe', $bio);
    }

    public static function invalidUserIdProvider(): Generator
    {
        yield 'invalid uuid' => ['invalid-uuid'];
        yield 'empty value' => [''];
    }

    public static function invalidNameProvider(): Generator
    {
        yield 'empty value' => [''];
        yield 'less then 3 characters long' => ['Jo'];
        yield 'more then 50 characters long' => [str_repeat('a', 51)];
    }

    public static function invalidBioProvider(): Generator
    {
        yield 'more then 300 characters long' => [str_repeat('b', 301)];
    }
}
