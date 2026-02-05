<?php

declare(strict_types=1);

namespace Twitter\IAM\Infrastructure\Persistence\MySQL\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Twitter\IAM\Domain\User\Exception\UserAlreadyExistsException;
use Twitter\IAM\Domain\User\Model\User;
use Twitter\IAM\Domain\User\Model\UserRepository;

final class MySQLUserRepository extends EntityRepository implements UserRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct($entityManager, $entityManager->getClassMetadata(User::class));
    }

    /**
     * @throws UserAlreadyExistsException
     */
    public function add(User $user): void
    {
        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new UserAlreadyExistsException($user->email());
        }
    }
}
