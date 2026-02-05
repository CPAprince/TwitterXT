<?php

declare(strict_types=1);

namespace Twitter\Tests\IAM\Domain\User\Model;

use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\IAM\Domain\User\Exception\InvalidPasswordException;
use Twitter\IAM\Domain\User\Model\PasswordHash;

#[Group('unit')]
#[CoversClass(PasswordHash::class)]
class PasswordHashTest extends TestCase
{
    #[Test]
    public function hashesAndVerifiesAValidPlainPassword(): void
    {
        $password = 'Pswd.123';
        $passwordHash = PasswordHash::fromPlainPassword($password);

        self::assertTrue(password_verify($password, (string) $passwordHash));
    }

    #[Test]
    public function rejectsATooShortPlainPassword(): void
    {
        self::expectException(InvalidPasswordException::class);

        PasswordHash::fromPlainPassword('Pswd.12');
    }

    #[Test]
    public function createsAValueObjectFromExistingHash(): void
    {
        $password = 'Pswd.123';
        $passwordHash = PasswordHash::fromPlainPassword($password);

        self::assertTrue(password_verify($password, (string) $passwordHash));
        self::assertEquals((string) $passwordHash, (string) PasswordHash::fromHash((string) $passwordHash));
    }

    #[Test]
    public function rejectsAnInvalidPasswordHash(): void
    {
        self::expectException(InvalidArgumentException::class);

        PasswordHash::fromHash('abracadabra');
    }

    #[Test]
    #[DataProvider('plainPasswordValidationProvider')]
    public function validatesPlainPasswordFormat(string $password, bool $shouldBeAccepted): void
    {
        if (!$shouldBeAccepted) {
            $this->expectException(InvalidPasswordException::class);
        }

        $hash = PasswordHash::fromPlainPassword($password);

        self::assertTrue(password_verify($password, (string) $hash));
    }

    public static function plainPasswordValidationProvider(): Generator
    {
        yield 'valid password' => ['Pswd.123', true];
        yield 'short password' => ['Ps.1a$', false];
        yield 'no uppercase letter' => ['pswd.123$', false];
        yield 'no lowercase letter' => ['PSWD.123$', false];
        yield 'no number' => ['Password.$', false];
        yield 'no special character' => ['Password123', false];
        yield 'valid with a different special char' => ['Abcd123!', true];
        yield 'valid with a dash and underscore' => ['Abcd-123_', true];
    }

    #[Test]
    #[DataProvider('passwordVerificationProvider')]
    public function verifiesPlainPasswordsCorrectly(string $input, bool $expected): void
    {
        $passwordHash = PasswordHash::fromPlainPassword('Pswd.123');

        self::assertSame($expected, $passwordHash->verify($input));
    }

    public static function passwordVerificationProvider(): Generator
    {
        yield 'valid password' => ['Pswd.123', true];
        yield 'empty password' => ['', false];
        yield 'short password' => ['Psd.123', false];
        yield 'password with no uppercase letter' => ['pswd.123', false];
        yield 'password with no lowercase letter' => ['PSWD.123', false];
        yield 'password with no number' => ['Pas.word', false];
    }
}
