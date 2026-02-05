<?php

declare(strict_types=1);

namespace Twitter\Tests\Registration\Application\UseCase\RegisterUser;

use Assert\LazyAssertionException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Twitter\IAM\Domain\User\Exception\InvalidEmailException;
use Twitter\IAM\Domain\User\Exception\InvalidPasswordException;
use Twitter\IAM\Domain\User\Exception\UserAlreadyExistsException;
use Twitter\Registration\Application\UseCase\RegisterUser\RegisterUserCommand;
use Twitter\Registration\Application\UseCase\RegisterUser\RegisterUserCommandHandler;

#[Group('component')]
#[CoversClass(RegisterUserCommandHandler::class)]
final class RegisterUserCommandHandlerTest extends TestCase
{
    private RegisterUserCommandHandler $handler;
    private EntityManagerInterface&MockObject $entityManager;
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->connection = $this->createMock(Connection::class);

        $this->handler = new RegisterUserCommandHandler(
            $this->entityManager,
        );
    }

    #[Test]
    public function createsUserAndProfileInSingleTransaction(): void
    {
        $this->entityManager
            ->expects(self::once())
            ->method('getConnection')
            ->willReturn($this->connection);

        $this->connection
            ->expects(self::once())
            ->method('beginTransaction');

        $this->connection
            ->expects(self::once())
            ->method('commit');

        $this->connection
            ->expects(self::never())
            ->method('rollBack');

        $this->entityManager
            ->expects(self::exactly(2))
            ->method('persist');

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        $result = $this->handler->handle(new RegisterUserCommand(
            email: 'test@example.com',
            password: 'Qwerty.123',
            name: 'John Doe',
            bio: 'Bio',
        ));

        self::assertNotEmpty($result->userId);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $result->userId,
        );
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function failsWhenEmailIsInvalid(): void
    {
        $this->expectException(InvalidEmailException::class);

        $this->entityManager
            ->expects(self::never())
            ->method('getConnection');

        $this->handler->handle(new RegisterUserCommand(
            email: 'invalid',
            password: 'Qwerty.123',
            name: 'John Doe',
            bio: null,
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function failsWhenPasswordIsInvalid(): void
    {
        $this->expectException(InvalidPasswordException::class);

        $this->entityManager
            ->expects(self::never())
            ->method('getConnection');

        $this->handler->handle(new RegisterUserCommand(
            email: 'test@example.com',
            password: 'invalid',
            name: 'John Doe',
            bio: null,
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function failsWhenProfileNameIsInvalid(): void
    {
        $this->expectException(LazyAssertionException::class);

        $this->entityManager
            ->expects(self::never())
            ->method('getConnection');

        $this->handler->handle(new RegisterUserCommand(
            email: 'test@example.com',
            password: 'Qwerty.123',
            name: 'ab',
            bio: null,
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function rollsBackWhenUserAlreadyExists(): void
    {
        $this->expectException(UserAlreadyExistsException::class);

        $this->entityManager
            ->expects(self::once())
            ->method('getConnection')
            ->willReturn($this->connection);

        $this->connection
            ->expects(self::once())
            ->method('beginTransaction');

        $this->connection
            ->expects(self::once())
            ->method('rollBack');

        $this->connection
            ->expects(self::never())
            ->method('commit');

        $this->entityManager
            ->expects(self::exactly(2))
            ->method('persist');

        $this->entityManager
            ->expects(self::once())
            ->method('flush')
            ->willThrowException($this->createMock(UniqueConstraintViolationException::class));

        $this->handler->handle(new RegisterUserCommand(
            email: 'test@example.com',
            password: 'Qwerty.123',
            name: 'John Doe',
            bio: null,
        ));
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function propagatesUnexpectedFailuresAndRollsBack(): void
    {
        $this->expectException(RuntimeException::class);

        $this->entityManager
            ->expects(self::once())
            ->method('getConnection')
            ->willReturn($this->connection);

        $this->connection
            ->expects(self::once())
            ->method('beginTransaction');

        $this->connection
            ->expects(self::once())
            ->method('rollBack');

        $this->connection
            ->expects(self::never())
            ->method('commit');

        $this->entityManager
            ->expects(self::exactly(2))
            ->method('persist');

        $this->entityManager
            ->expects(self::once())
            ->method('flush')
            ->willThrowException(new RuntimeException());

        $this->handler->handle(new RegisterUserCommand(
            email: 'test@example.com',
            password: 'Qwerty.123',
            name: 'John Doe',
            bio: null,
        ));
    }
}
