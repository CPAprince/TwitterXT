<?php

declare(strict_types=1);

namespace Twitter\Tests\Like\Infrastructure\Persistence\MySQL\Repository;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\Like\Domain\Like\Exception\LikeAlreadyExistsException;
use Twitter\Like\Domain\Like\Model\Like;
use Twitter\Like\Infrastructure\Persistence\MySQL\Repository\MySQLLikeRepository;

#[Group('unit')]
#[CoversClass(MySQLLikeRepository::class)]
final class MySQLLikeRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private MySQLLikeRepository $repository;

    protected function setUp(): void
    {
        // Arrange
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = new MySQLLikeRepository($this->entityManager);
    }

    #[Test]
    public function addPersistsAndFlushesLike(): void
    {
        // Arrange
        $like = Like::create(
            '019b5f3f-d110-7908-9177-5df439942a8b',
            '550e8400-e29b-41d4-a716-446655440000',
        );

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($like);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        // Act
        $this->repository->add($like);
    }

    #[Test]
    public function addThrowsLikeAlreadyExistsExceptionOnUniqueConstraint(): void
    {
        // Arrange
        $like = Like::create(
            '019b5f3f-d110-7908-9177-5df439942a8b',
            '550e8400-e29b-41d4-a716-446655440000',
        );

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($like);

        $this->entityManager
            ->expects(self::once())
            ->method('flush')
            ->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        // Act
        $this->expectException(LikeAlreadyExistsException::class);
        $this->repository->add($like);

        // Assert (exception)
    }

    #[Test]
    public function removeDeletesAndFlushesLike(): void
    {
        // Arrange
        $like = Like::create(
            '019b5f3f-d110-7908-9177-5df439942a8b',
            '550e8400-e29b-41d4-a716-446655440000',
        );

        $this->entityManager
            ->expects(self::once())
            ->method('remove')
            ->with($like);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        // Act
        $this->repository->remove($like);
    }

    #[Test]
    public function findOneByTweetAndUserReturnsLikeWhenExists(): void
    {
        // Arrange
        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $like = Like::create($tweetId, $userId);

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(Like::class, [
                'tweetId' => $tweetId,
                'userId' => $userId,
            ])
            ->willReturn($like);

        // Act
        $result = $this->repository->findOneByTweetAndUser($tweetId, $userId);

        // Assert
        self::assertSame($like, $result);
    }

    #[Test]
    public function findOneByTweetAndUserReturnsNullWhenNotExists(): void
    {
        // Arrange
        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $userId = '550e8400-e29b-41d4-a716-446655440000';

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(Like::class, [
                'tweetId' => $tweetId,
                'userId' => $userId,
            ])
            ->willReturn(null);

        // Act
        $result = $this->repository->findOneByTweetAndUser($tweetId, $userId);

        // Assert
        self::assertNull($result);
    }
}
