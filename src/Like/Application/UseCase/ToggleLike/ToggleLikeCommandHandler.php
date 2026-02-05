<?php

declare(strict_types=1);

namespace Twitter\Like\Application\UseCase\ToggleLike;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twitter\Like\Domain\Like\Event\TweetWasLiked;
use Twitter\Like\Domain\Like\Event\TweetWasUnliked;
use Twitter\Like\Domain\Like\Exception\LikeAlreadyExistsException;
use Twitter\Like\Domain\Like\Model\Like;
use Twitter\Like\Domain\Like\Model\LikeRepository;

final readonly class ToggleLikeCommandHandler
{
    public function __construct(
        private LikeRepository $likeRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @throws LikeAlreadyExistsException
     */
    public function handle(ToggleLikeCommand $command): ToggleLikeCommandResult
    {
        $existingLike = $this->likeRepository->findOneByTweetAndUser(
            $command->tweetId,
            $command->userId,
        );

        if (null !== $existingLike) {
            $this->likeRepository->remove($existingLike);
            $this->eventDispatcher->dispatch(new TweetWasUnliked($command->tweetId, $command->userId));

            return new ToggleLikeCommandResult(false);
        }

        $newLike = Like::create($command->tweetId, $command->userId);
        $this->likeRepository->add($newLike);

        $this->eventDispatcher->dispatch(new TweetWasLiked($command->tweetId, $command->userId));

        return new ToggleLikeCommandResult(true);
    }
}
