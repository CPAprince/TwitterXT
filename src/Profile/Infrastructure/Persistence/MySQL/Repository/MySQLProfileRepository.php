<?php

declare(strict_types=1);

namespace Twitter\Profile\Infrastructure\Persistence\MySQL\Repository;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\EntityIdentityCollisionException;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Twitter\Profile\Domain\Profile\Exception\ProfileAlreadyExistsException;
use Twitter\Profile\Domain\Profile\Exception\ProfileNotFoundException;
use Twitter\Profile\Domain\Profile\Exception\UserNotFoundException;
use Twitter\Profile\Domain\Profile\Model\Profile;
use Twitter\Profile\Domain\Profile\Model\ProfileRepository;

final readonly class MySQLProfileRepository implements ProfileRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @throws UserNotFoundException
     * @throws ProfileAlreadyExistsException
     */
    public function add(Profile $profile): void
    {
        try {
            $this->entityManager->persist($profile);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException|EntityIdentityCollisionException) {
            throw new ProfileAlreadyExistsException($profile->userId());
        } catch (ForeignKeyConstraintViolationException) {
            throw new UserNotFoundException($profile->userId());
        }
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ProfileNotFoundException
     */
    public function getByUserId(string $userId): Profile
    {
        $profile = $this->entityManager->find(Profile::class, $userId);
        if (null === $profile) {
            throw new ProfileNotFoundException($userId);
        }

        return $profile;
    }

    public function findAllByUserIds(array $userIds): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('p')
            ->from(Profile::class, 'p')
            ->where('p.userId IN (:userIds)')
            ->setParameter('userIds', $userIds)
            ->getQuery()
            ->getResult();
    }
}
