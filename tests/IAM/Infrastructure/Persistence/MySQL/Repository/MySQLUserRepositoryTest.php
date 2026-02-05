<?php

declare(strict_types=1);

namespace Twitter\Tests\IAM\Infrastructure\Persistence\MySQL\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\IAM\Domain\User\Exception\UserAlreadyExistsException;
use Twitter\IAM\Domain\User\Model\Email;
use Twitter\IAM\Domain\User\Model\PasswordHash;
use Twitter\IAM\Domain\User\Model\User;
use Twitter\IAM\Infrastructure\Persistence\MySQL\Repository\MySQLUserRepository;

#[Group('unit')]
#[CoversClass(MySQLUserRepository::class)]
final class MySQLUserRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private MySQLUserRepository $repository;

    protected function setUp(): void
    {
        // Arrange
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->entityManager
            ->method('getClassMetadata')
            ->with(User::class)
            ->willReturn(new ClassMetadata(User::class));

        $this->repository = new MySQLUserRepository($this->entityManager);
    }

    #[Test]
    public function addPersistsAndFlushesUser(): void
    {
        // Arrange
        $user = User::create(
            Email::fromString('test@example.com'),
            PasswordHash::fromPlainPassword('Pswd.123'),
        );

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($user);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        // Act
        $this->repository->add($user);
    }

    #[Test]
    public function addThrowsUserAlreadyExistsExceptionOnUniqueConstraint(): void
    {
        // Arrange
        $user = User::create(
            Email::fromString('test@example.com'),
            PasswordHash::fromPlainPassword('Pswd.123'),
        );

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($user);

        $this->entityManager
            ->expects(self::once())
            ->method('flush')
            ->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        // Act
        $this->expectException(UserAlreadyExistsException::class);
        $this->repository->add($user);

        // Assert (exception)
    }
}
