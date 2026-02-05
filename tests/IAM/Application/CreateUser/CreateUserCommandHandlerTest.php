<?php

declare(strict_types=1);

namespace Twitter\Tests\IAM\Application\CreateUser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Twitter\IAM\Application\CreateUser\CreateUserCommand;
use Twitter\IAM\Application\CreateUser\CreateUserCommandHandler;
use Twitter\IAM\Domain\User\Exception\InvalidEmailException;
use Twitter\IAM\Domain\User\Exception\InvalidPasswordException;
use Twitter\IAM\Domain\User\Exception\UserAlreadyExistsException;
use Twitter\IAM\Domain\User\Model\UserRepository;

#[Group('unit')]
#[CoversClass(CreateUserCommandHandler::class)]
class CreateUserCommandHandlerTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private CreateUserCommandHandler $handler;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->handler = new CreateUserCommandHandler($this->userRepository);
    }

    #[Test]
    public function createsAUserAndReturnsAnIdentifier(): void
    {
        $this->userRepository
            ->expects(self::once())
            ->method('add');

        $result = $this->handler->handle(
            new CreateUserCommand(
                'test@example.com',
                'Qwerty.123',
            ),
        );

        self::assertNotEmpty($result->userId);
    }

    #[Test]
    public function failsWhenEmailIsInvalid(): void
    {
        $this->expectException(InvalidEmailException::class);

        $this->userRepository
            ->expects(self::never())
            ->method('add');

        $this->handler->handle(
            new CreateUserCommand(
                'test@example',
                'Qwerty.123',
            ),
        );
    }

    #[Test]
    public function failsWhenPasswordIsInvalid(): void
    {
        $this->expectException(InvalidPasswordException::class);

        $this->userRepository
            ->expects(self::never())
            ->method('add');

        $this->handler->handle(
            new CreateUserCommand(
                'test@example.com',
                'qwerty1',
            ),
        );
    }

    #[Test]
    public function failsWhenUserAlreadyExists(): void
    {
        $this->expectException(UserAlreadyExistsException::class);

        $this->userRepository
            ->expects(self::once())
            ->method('add')
            ->willThrowException(new UserAlreadyExistsException('test@example.com'));

        $this->handler->handle(
            new CreateUserCommand(
                'test@example.com',
                'Qwerty.123',
            ),
        );
    }

    #[Test]
    public function propagatesUnexpectedRepositoryFailures(): void
    {
        $this->expectException(RuntimeException::class);

        $this->userRepository
            ->expects(self::once())
            ->method('add')
            ->willThrowException(new RuntimeException());

        $this->handler->handle(
            new CreateUserCommand(
                'test@example.com',
                'Qwerty.123',
            ),
        );
    }
}
