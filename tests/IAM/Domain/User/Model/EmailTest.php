<?php

declare(strict_types=1);

namespace Twitter\Tests\IAM\Domain\User\Model;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\IAM\Domain\User\Exception\InvalidEmailException;
use Twitter\IAM\Domain\User\Model\Email;

#[Group('unit')]
#[CoversClass(Email::class)]
class EmailTest extends TestCase
{
    #[Test]
    #[DataProvider('validEmailProvider')]
    public function canBeCreatedFromAValidEmail(string $value): void
    {
        $email = Email::fromString($value);

        self::assertSame($value, (string) $email);
    }

    public static function validEmailProvider(): Generator
    {
        yield 'simple email' => ['test@example.com'];
        yield 'with subdomain' => ['user@mail.example.com'];
        yield 'with plus alias' => ['user+alias@example.com'];
        yield 'numeric local part' => ['123@example.com'];
        yield 'dash and dot' => ['first.last-user@example.co.uk'];
    }

    #[Test]
    public function throwsWhenCreatedFromAnInvalidEmailString(): void
    {
        $this->expectException(InvalidEmailException::class);

        Email::fromString('test@example');
    }

    #[Test]
    public function twoEmailsWithTheSameValueAreEqual(): void
    {
        $value = 'test@example.com';

        $a = Email::fromString($value);
        $b = Email::fromString($value);

        self::assertSame((string) $a, (string) $b);
    }

    #[Test]
    public function emailsWithDifferentValuesAreNotEqual(): void
    {
        $a = Email::fromString('a@example.com');
        $b = Email::fromString('b@example.com');

        self::assertNotSame((string) $a, (string) $b);
    }
}
