<?php

declare(strict_types=1);

namespace Twitter\Like\Infrastructure\Persistence\MySQL\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Twitter\Like\Domain\Like\Exception\LikeAlreadyExistsException;
use Twitter\Like\Domain\Like\Model\Like;
use Twitter\Like\Domain\Like\Model\LikeRepository;

final readonly class MySQLLikeRepository implements LikeRepository
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @throws LikeAlreadyExistsException
     */
    #[Override]
    public function add(Like $like): void
    {
        try {
            $this->entityManager->persist($like);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException) {
            throw new LikeAlreadyExistsException($like->tweetId(), $like->userId());
        }
    }

    #[Override]
    public function remove(Like $like): void
    {
        $this->entityManager->remove($like);
        $this->entityManager->flush();
    }

    #[Override]
    public function findOneByTweetAndUser(string $tweetId, string $userId): ?Like
    {
        return $this->entityManager->find(Like::class, [
            'tweetId' => $tweetId,
            'userId' => $userId,
        ]);
    }
}
