<?php

declare(strict_types=1);

namespace Twitter\Like\Infrastructure\Lock\Decorator;

use Override;
use Symfony\Component\Lock\LockFactory;
use Twitter\Like\Application\UseCase\ToggleLike\ToggleLikeCommand;
use Twitter\Like\Application\UseCase\ToggleLike\ToggleLikeCommandHandlerInterface;
use Twitter\Like\Application\UseCase\ToggleLike\ToggleLikeCommandResult;
use Twitter\Like\Infrastructure\Lock\Exception\LikeActionLockedException;

final readonly class ToggleLikeCommandHandlerDecorator implements ToggleLikeCommandHandlerInterface
{
    public function __construct(
        private ToggleLikeCommandHandlerInterface $inner,
        private LockFactory $lockFactory,
        private int $ttlSeconds,
    ) {}

    /**
     * @throws LikeActionLockedException
     */
    #[Override]
    public function handle(ToggleLikeCommand $command): ToggleLikeCommandResult
    {
        $lock = $this->lockFactory->createLock(
            $this->lockLikeKey($command),
            $this->ttlSeconds,
        );

        if (!$lock->acquire(false)) {
            throw new LikeActionLockedException($command->tweetId, $command->userId);
        }

        try {
            return $this->inner->handle($command);
        } finally {
            $lock->release();
        }
    }

    private function lockLikeKey(ToggleLikeCommand $command): string
    {
        return sprintf(
            'lock_like_user_%s_tweet_%s',
            $command->userId,
            $command->tweetId,
        );
    }
}
