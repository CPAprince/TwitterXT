<?php

declare(strict_types=1);

namespace Twitter\Tests\Profile\Infrastructure\Persistence\MySQL\Repository;

use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\EntityIdentityCollisionException;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Generator;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use stdClass;
use Twitter\Profile\Domain\Profile\Exception\ProfileAlreadyExistsException;
use Twitter\Profile\Domain\Profile\Exception\ProfileNotFoundException;
use Twitter\Profile\Domain\Profile\Exception\UserNotFoundException;
use Twitter\Profile\Domain\Profile\Model\Profile;
use Twitter\Profile\Infrastructure\Persistence\MySQL\Repository\MySQLProfileRepository;

#[Group('unit')]
#[CoversClass(MySQLProfileRepository::class)]
final class MySQLProfileRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private MySQLProfileRepository $repository;

    protected function setUp(): void
    {
        // Arrange
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = new MySQLProfileRepository($this->entityManager);
    }

    #[Test]
    public function addPersistsAndFlushesProfile(): void
    {
        // Arrange
        $profile = Profile::create(
            '019b5f3f-d110-7908-9177-5df439942a8b',
            'John Doe',
            'Bio',
        );

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($profile);

        $this->entityManager
            ->expects(self::once())
            ->method('flush');

        // Act
        $this->repository->add($profile);
    }

    #[Test]
    #[DataProvider('duplicateProfileProvider')]
    public function addThrowsProfileAlreadyExistsExceptionWhenDuplicate(string $type): void
    {
        // Arrange
        $profile = Profile::create(
            '019b5f3f-d110-7908-9177-5df439942a8b',
            'John Doe',
            'Bio',
        );

        $throwable = match ($type) {
            'unique' => $this->createStub(UniqueConstraintViolationException::class),
            'collision' => EntityIdentityCollisionException::create(new stdClass(), new stdClass(), 'id_hash'),
            default => throw new LogicException('Unknown type: '.$type),
        };

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($profile);

        $this->entityManager
            ->expects(self::once())
            ->method('flush')
            ->willThrowException($throwable);

        // Act
        $this->expectException(ProfileAlreadyExistsException::class);
        $this->repository->add($profile);

        // Assert (exception)
    }

    public static function duplicateProfileProvider(): Generator
    {
        yield 'unique constraint violation' => ['unique'];
        yield 'entity identity collision' => ['collision'];
    }

    #[Test]
    public function addThrowsUserNotFoundExceptionOnForeignKeyViolation(): void
    {
        // Arrange
        $profile = Profile::create(
            '019b5f3f-d110-7908-9177-5df439942a8b',
            'John Doe',
            'Bio',
        );

        $this->entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($profile);

        $this->entityManager
            ->expects(self::once())
            ->method('flush')
            ->willThrowException($this->createStub(ForeignKeyConstraintViolationException::class));

        // Act
        $this->expectException(UserNotFoundException::class);
        $this->repository->add($profile);

        // Assert (exception)
    }

    #[Test]
    public function getByUserIdReturnsProfileWhenExists(): void
    {
        // Arrange
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $profile = Profile::create($userId, 'John Doe', 'Bio');

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(Profile::class, $userId)
            ->willReturn($profile);

        // Act
        $result = $this->repository->getByUserId($userId);

        // Assert
        self::assertSame($profile, $result);
    }

    #[Test]
    public function getByUserIdThrowsProfileNotFoundExceptionWhenNotExists(): void
    {
        // Arrange
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $this->entityManager
            ->expects(self::once())
            ->method('find')
            ->with(Profile::class, $userId)
            ->willReturn(null);

        // Act
        $this->expectException(ProfileNotFoundException::class);
        $this->repository->getByUserId($userId);

        // Assert (exception)
    }

    #[Test]
    public function findAllByUserIdsReturnsProfiles(): void
    {
        // Arrange
        $userIds = [
            '019b5f3f-d110-7908-9177-5df439942a8b',
            '550e8400-e29b-41d4-a716-446655440000',
        ];

        $expected = [
            Profile::create($userIds[0], 'John Doe', 'Bio'),
            Profile::create($userIds[1], 'Jane Doe', 'Bio'),
        ];

        $query = $this->createMock(Query::class);
        $query
            ->expects(self::once())
            ->method('getResult')
            ->willReturn($expected);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())->method('select')->with('p')->willReturnSelf();
        $qb->expects(self::once())->method('from')->with(Profile::class, 'p')->willReturnSelf();
        $qb->expects(self::once())->method('where')->with('p.userId IN (:userIds)')->willReturnSelf();
        $qb->expects(self::once())->method('setParameter')->with('userIds', $userIds)->willReturnSelf();
        $qb->expects(self::once())->method('getQuery')->willReturn($query);

        $this->entityManager
            ->expects(self::once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        // Act
        $result = $this->repository->findAllByUserIds($userIds);

        // Assert
        self::assertSame($expected, $result);
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
}
