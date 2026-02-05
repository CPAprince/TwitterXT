<?php

declare(strict_types=1);

namespace Twitter\IAM\Application\CreateUser;

use Twitter\IAM\Domain\User\Exception\InvalidEmailException;
use Twitter\IAM\Domain\User\Exception\InvalidPasswordException;
use Twitter\IAM\Domain\User\Model\Email;
use Twitter\IAM\Domain\User\Model\PasswordHash;
use Twitter\IAM\Domain\User\Model\User;
use Twitter\IAM\Domain\User\Model\UserRepository;

final readonly class CreateUserCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    /**
     * @throws InvalidEmailException
     * @throws InvalidPasswordException
     */
    public function handle(CreateUserCommand $command): CreateUserCommandResult
    {
        $user = User::create(
            Email::fromString($command->email),
            PasswordHash::fromPlainPassword($command->password),
        );

        $this->userRepository->add($user);

        return new CreateUserCommandResult($user->id());
    }
}
