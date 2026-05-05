<?php

declare(strict_types=1);

namespace Twitter\Tests\Like\Infrastructure\Lock\Decorator;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Twitter\Like\Application\UseCase\ToggleLike\ToggleLikeCommand;
use Twitter\Like\Application\UseCase\ToggleLike\ToggleLikeCommandHandlerInterface;
use Twitter\Like\Application\UseCase\ToggleLike\ToggleLikeCommandResult;
use Twitter\Like\Infrastructure\Lock\Decorator\ToggleLikeCommandHandlerDecorator;
use Twitter\Like\Infrastructure\Lock\Exception\LikeActionLockedException;

#[Group('unit')]
#[CoversMethod(ToggleLikeCommandHandlerDecorator::class, 'handle')]
final class ToggleLikeCommandHandlerDecoratorTest extends TestCase
{
    private ToggleLikeCommandHandlerDecorator $handler;
    private LockFactory&MockObject $lockFactory;
    private SharedLockInterface&MockObject $lock;
    private ToggleLikeCommandHandlerInterface&MockObject $inner;
    private ToggleLikeCommand $command;

    protected function setUp(): void
    {
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->lock = $this->createMock(SharedLockInterface::class);
        $this->inner = $this->createMock(ToggleLikeCommandHandlerInterface::class);

        $this->handler = new ToggleLikeCommandHandlerDecorator(
            $this->inner,
            $this->lockFactory,
            5,
        );

        $this->command = new ToggleLikeCommand(
            'tweet-123',
            'user-456',
        );
    }

    #[Test]
    public function throwsExceptionWhenLockIsNotAcquired(): void
    {
        $this->lockFactory
            ->expects(self::once())
            ->method('createLock')
            ->with('lock_like_user_user-456_tweet_tweet-123', 5)
            ->willReturn($this->lock);

        $this->lock
            ->expects(self::once())
            ->method('acquire')
            ->with(false)
            ->willReturn(false);

        $this->lock
            ->expects(self::never())
            ->method('release');

        $this->inner
            ->expects(self::never())
            ->method('handle');

        $this->expectException(LikeActionLockedException::class);

        $this->handler->handle($this->command);
    }

    #[Test]
    public function delegatesToInnerHandlerWhenLockIsAcquired(): void
    {
        $expectedResult = new ToggleLikeCommandResult(true);

        $this->lockFactory
            ->expects(self::once())
            ->method('createLock')
            ->with('lock_like_user_user-456_tweet_tweet-123', 5)
            ->willReturn($this->lock);

        $this->lock
            ->expects(self::once())
            ->method('acquire')
            ->with(false)
            ->willReturn(true);

        $this->inner
            ->expects(self::once())
            ->method('handle')
            ->with($this->command)
            ->willReturn($expectedResult);

        $this->lock
            ->expects(self::once())
            ->method('release');

        self::assertSame($expectedResult, $this->handler->handle($this->command));
    }

    #[Test]
    public function releasesLockWhenInnerHandlerThrows(): void
    {
        $this->lockFactory
            ->expects(self::once())
            ->method('createLock')
            ->with('lock_like_user_user-456_tweet_tweet-123', 5)
            ->willReturn($this->lock);

        $this->lock
            ->expects(self::once())
            ->method('acquire')
            ->with(false)
            ->willReturn(true);

        $this->inner
            ->expects(self::once())
            ->method('handle')
            ->with($this->command)
            ->willThrowException(new RuntimeException('Boom'));

        $this->lock
            ->expects(self::once())
            ->method('release');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Boom');

        $this->handler->handle($this->command);
    }
}
