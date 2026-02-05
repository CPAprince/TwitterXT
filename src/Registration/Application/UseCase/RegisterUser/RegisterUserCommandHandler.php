<?php

declare(strict_types=1);

namespace Twitter\Registration\Application\UseCase\RegisterUser;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;
use Twitter\IAM\Domain\User\Exception\UserAlreadyExistsException;
use Twitter\IAM\Domain\User\Model\Email;
use Twitter\IAM\Domain\User\Model\PasswordHash;
use Twitter\IAM\Domain\User\Model\User;
use Twitter\Profile\Domain\Profile\Exception\UserNotFoundException;
use Twitter\Profile\Domain\Profile\Model\Profile;

final readonly class RegisterUserCommandHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function handle(RegisterUserCommand $command): RegisterUserCommandResult
    {
        $email = Email::fromString($command->email);
        $passwordHash = PasswordHash::fromPlainPassword($command->password);

        try {
            $user = User::create($email, $passwordHash);
            $userId = $user->id();
            $profile = Profile::create(
                userId: $user->id(),
                name: $command->name,
                bio: $command->bio,
            );

            $connection = $this->entityManager->getConnection();
            $connection->beginTransaction();

            try {
                $this->entityManager->persist($user);
                $this->entityManager->persist($profile);
                $this->entityManager->flush();

                $connection->commit();
            } catch (Throwable $e) {
                $connection->rollBack();
                throw $e;
            }

            return new RegisterUserCommandResult($userId);
        } catch (UniqueConstraintViolationException) {
            throw new UserAlreadyExistsException((string) $email);
        } catch (ForeignKeyConstraintViolationException) {
            // Should be impossible for fresh registration, but keep consistent error mapping.
            throw new UserNotFoundException($userId);
        }
    }
}
