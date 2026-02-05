<?php

declare(strict_types=1);

namespace Twitter\Tweet\Infrastructure\Persistence\MySQL\Repository;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;
use Twitter\Tweet\Domain\Tweet\Exception\UserNotFoundException;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

final readonly class MySQLTweetRepository implements TweetRepository
{
    private const int DEFAULT_LIMIT = 100;

    public function __construct(private EntityManagerInterface $entityManager) {}

    /**
     * @throws UserNotFoundException
     */
    public function add(Tweet $tweet): void
    {
        try {
            $this->entityManager->persist($tweet);
            $this->entityManager->flush();
        } catch (ForeignKeyConstraintViolationException) {
            throw new UserNotFoundException($tweet->userId());
        }
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    /**
     * @throws OptimisticLockException
     * @throws TweetNotFoundException
     * @throws ORMException
     */
    public function getById(string $tweetId): Tweet
    {
        $tweet = $this->entityManager->find(Tweet::class, $tweetId);
        if (null === $tweet) {
            throw new TweetNotFoundException($tweetId);
        }

        return $tweet;
    }

    private function resolvePagination(int $limit, int $page): array
    {
        $limit = $limit <= 0 ? self::DEFAULT_LIMIT : min(self::DEFAULT_LIMIT, $limit);
        $offset = max(0, ($page - 1) * $limit);

        return [$limit, $offset];
    }

    public function getAllTweets(int $limit = self::DEFAULT_LIMIT, int $page = 1): array
    {
        [$limit, $offset] = $this->resolvePagination($limit, $page);

        return $this->entityManager
            ->getRepository(Tweet::class)
            ->findBy([], ['createdAt' => 'DESC'], $limit, $offset);
    }

    public function getUserTweets(string $userId, int $limit = self::DEFAULT_LIMIT, int $page = 1): array
    {
        [$limit, $offset] = $this->resolvePagination($limit, $page);
        $binaryUserId = pack('H*', str_replace('-', '', $userId));

        return $this->entityManager
            ->getRepository(Tweet::class)
            ->findBy(['userId' => $binaryUserId], ['createdAt' => 'DESC'], $limit, $offset);
    }
}
