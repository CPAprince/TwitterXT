<?php

declare(strict_types=1);

namespace Twitter\Tests\IAM\Domain\User\Model;

use DateTimeImmutable;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Twitter\IAM\Domain\User\Model\Email;
use Twitter\IAM\Domain\User\Model\PasswordHash;
use Twitter\IAM\Domain\User\Model\User;

#[Group('unit')]
#[CoversClass(User::class)]
final class UserTest extends TestCase
{
    #[Test]
    public function createReturnsUserWithCorrectProperties(): void
    {
        // Arrange
        $email = Email::fromString('test@example.com');
        $passwordHash = PasswordHash::fromPlainPassword('Pswd.123');

        // Act
        $user = User::create($email, $passwordHash);

        // Assert
        self::assertNotEmpty($user->id());
        self::assertSame('test@example.com', $user->email());
        self::assertSame((string) $passwordHash, $user->passwordHash());
        self::assertInstanceOf(DateTimeImmutable::class, $user->createdAt());
        self::assertInstanceOf(DateTimeImmutable::class, $user->updatedAt());
        self::assertContains('ROLE_USER', $user->roles());
    }

    #[Test]
    public function getUserIdentifierReturnsEmail(): void
    {
        // Arrange
        $email = Email::fromString('test@example.com');
        $passwordHash = PasswordHash::fromPlainPassword('Pswd.123');
        $user = User::create($email, $passwordHash);

        // Act
        $identifier = $user->getUserIdentifier();

        // Assert
        self::assertSame('test@example.com', $identifier);
    }

    #[Test]
    public function getPasswordReturnsPasswordHash(): void
    {
        // Arrange
        $passwordHash = PasswordHash::fromPlainPassword('Pswd.123');
        $user = User::create(Email::fromString('test@example.com'), $passwordHash);

        // Act
        $password = $user->getPassword();

        // Assert
        self::assertSame((string) $passwordHash, $password);
    }

    #[Test]
    #[DataProvider('rolesProvider')]
    public function getRolesAlwaysIncludesRoleUser(array $roles): void
    {
        // Arrange
        $user = $this->userWithRoles($roles);

        // Act
        $computedRoles = $user->getRoles();

        // Assert
        self::assertContains('ROLE_USER', $computedRoles);
    }

    #[Test]
    public function getRolesDeduplicatesRoles(): void
    {
        // Arrange
        $user = $this->userWithRoles(['ROLE_USER', 'ROLE_USER', 'ROLE_ADMIN', 'ROLE_ADMIN']);

        // Act
        $computedRoles = $user->getRoles();

        // Assert
        self::assertSame(['ROLE_USER', 'ROLE_ADMIN'], array_values($computedRoles));
    }

    public static function rolesProvider(): Generator
    {
        yield 'empty roles' => [[]];
        yield 'already has role user' => [['ROLE_USER']];
        yield 'custom role only' => [['ROLE_ADMIN']];
        yield 'multiple roles' => [['ROLE_ADMIN', 'ROLE_MODERATOR']];
    }

    private function userWithRoles(array $roles): User
    {
        // Arrange
        $email = Email::fromString('test@example.com');
        $passwordHash = PasswordHash::fromPlainPassword('Pswd.123');
        $user = User::create($email, $passwordHash);

        // Act (mutate via reflection; domain has no public role mutator, but we want to cover getRoles logic)
        $ref = new ReflectionClass($user);
        $rolesProp = $ref->getProperty('roles');
        $rolesProp->setValue($user, $roles);

        // Assert
        return $user;
    }
}
