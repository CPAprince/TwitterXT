<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Infrastructure\Persistence\MySQL\Repository;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;
use Twitter\Tweet\Domain\Tweet\Exception\UserNotFoundException;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Infrastructure\Persistence\MySQL\Repository\MySQLTweetRepository;

#[Group('unit')]
#[CoversClass(MySQLTweetRepository::class)]
final class MySQLTweetRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private MySQLTweetRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = new MySQLTweetRepository($this->entityManager);
    }

    #[Test]
    public function getByIdReturnsTweetWhenItExists(): void
    {
        // Arrange
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $tweet = Tweet::create($userId, 'Hello');
        $tweetId = $tweet->id();

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(Tweet::class, $tweetId)
            ->willReturn($tweet);

        // Act
        $result = $this->repository->getById($tweetId);

        // Assert
        self::assertSame($tweet, $result);
    }

    #[Test]
    public function getByIdThrowsTweetNotFoundExceptionWhenTweetDoesNotExist(): void
    {
        // Arrange
        $tweetId = '019b5f41-0e5b-7f65-8b7a-0f9c0b3b3c11';

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(Tweet::class, $tweetId)
            ->willReturn(null);

        // Act
        $this->expectException(TweetNotFoundException::class);
        $this->repository->getById($tweetId);

        // Assert (exception)
    }

    #[Test]
    public function addPersistsAndFlushesTweet(): void
    {
        // Arrange
        $tweet = Tweet::create(
            '019b5f3f-d110-7908-9177-5df439942a8b',
            'Hello',
        );

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($tweet);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        // Act
        $this->repository->add($tweet);
    }

    #[Test]
    public function addThrowsUserNotFoundExceptionOnForeignKeyViolation(): void
    {
        // Arrange
        $tweet = Tweet::create(
            '019b5f3f-d110-7908-9177-5df439942a8b',
            'Hello',
        );

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($tweet);

        $this->entityManager
            ->expects(self::once())
            ->method('flush')
            ->willThrowException($this->createStub(ForeignKeyConstraintViolationException::class));

        // Act
        $this->expectException(UserNotFoundException::class);
        $this->repository->add($tweet);

        // Assert (exception)
    }

    #[Test]
    public function flushCallsEntityManagerFlush(): void
    {
        // Arrange
        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        // Act
        $this->repository->flush();
    }

    #[Test]
    public function getAllTweetsReturnsLatestTweets(): void
    {
        // Arrange
        $repo = $this->createMock(EntityRepository::class);
        $expected = [Tweet::create('019b5f3f-d110-7908-9177-5df439942a8b', 'Hello')];

        $this->entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(Tweet::class)
            ->willReturn($repo);

        $repo
            ->expects(self::once())
            ->method('findBy')
            ->with([], ['createdAt' => 'DESC'], 10, 0)
            ->willReturn($expected);

        // Act
        $result = $this->repository->getAllTweets(limit: 10, page: 1);

        // Assert
        self::assertSame($expected, $result);
    }

    #[Test]
    public function getUserTweetsFiltersByUserId(): void
    {
        // Arrange
        $repo = $this->createMock(EntityRepository::class);
        $expected = [Tweet::create('019b5f3f-d110-7908-9177-5df439942a8b', 'Hello')];

        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $expectedBinaryUserId = pack('H*', str_replace('-', '', $userId));

        $this->entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(Tweet::class)
            ->willReturn($repo);

        $repo
            ->expects(self::once())
            ->method('findBy')
            ->with(['userId' => $expectedBinaryUserId], ['createdAt' => 'DESC'], 10, 0)
            ->willReturn($expected);

        // Act
        $result = $this->repository->getUserTweets(userId: $userId, limit: 10, page: 1);

        // Assert
        self::assertSame($expected, $result);
    }

    #[Test]
    #[DataProvider('paginationProvider')]
    public function getAllTweetsHandlesInvalidLimitAndPage(int $limit, int $page, int $expectedLimit, int $expectedOffset): void
    {
        // Arrange
        $repo = $this->createMock(EntityRepository::class);
        $expected = [Tweet::create('019b5f3f-d110-7908-9177-5df439942a8b', 'Hello')];

        $this->entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(Tweet::class)
            ->willReturn($repo);

        $repo
            ->expects(self::once())
            ->method('findBy')
            ->with([], ['createdAt' => 'DESC'], $expectedLimit, $expectedOffset)
            ->willReturn($expected);

        // Act
        $result = $this->repository->getAllTweets(limit: $limit, page: $page);

        // Assert
        self::assertSame($expected, $result);
    }

    public static function paginationProvider(): Generator
    {
        yield 'limit below zero uses 100, page 1 => offset 0' => [-5, 1, 100, 0];
        yield 'limit above 100 is capped to 100' => [500, 1, 100, 0];
        yield 'page below 1 clamps offset to 0' => [10, 0, 10, 0];
        yield 'page negative clamps offset to 0' => [10, -5, 10, 0];
        yield 'page 2 offsets by limit' => [10, 2, 10, 10];
    }
}
