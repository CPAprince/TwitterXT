<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Application\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twitter\Like\Domain\Like\Event\TweetWasLiked;
use Twitter\Like\Domain\Like\Event\TweetWasUnliked;
use Twitter\Tweet\Application\EventSubscriber\UpdateTweetLikesCountSubscriber;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

#[Group('unit')]
#[CoversClass(UpdateTweetLikesCountSubscriber::class)]
final class UpdateTweetLikesCountSubscriberTest extends TestCase
{
    private UpdateTweetLikesCountSubscriber $subscriber;
    private TweetRepository $tweetRepository;

    protected function setUp(): void
    {
        $this->tweetRepository = $this->createStub(TweetRepository::class);
        $this->subscriber = new UpdateTweetLikesCountSubscriber($this->tweetRepository);
    }

    #[Test]
    public function getSubscribedEventsReturnsCorrectMapping(): void
    {
        // Act
        $events = UpdateTweetLikesCountSubscriber::getSubscribedEvents();

        // Assert
        self::assertSame([
            TweetWasLiked::class => 'onTweetLiked',
            TweetWasUnliked::class => 'onTweetUnliked',
        ], $events);
    }

    #[Test]
    public function onTweetLikedIncreasesCountAndSavesTweet(): void
    {
        $this->tweetRepository = $this->createMock(TweetRepository::class);
        $this->subscriber = new UpdateTweetLikesCountSubscriber($this->tweetRepository);

        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $event = new TweetWasLiked($tweetId, $userId);

        $tweet = Tweet::create($userId, 'Content');

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willReturn($tweet);

        $this->tweetRepository
            ->expects(self::once())
            ->method('add')
            ->with(
                self::callback(function (Tweet $savedTweet): bool {
                    return 1 === $savedTweet->likes();
                }),
            );

        $this->subscriber->onTweetLiked($event);
    }

    #[Test]
    public function onTweetUnlikedDecreasesCountAndSavesTweet(): void
    {
        $this->tweetRepository = $this->createMock(TweetRepository::class);
        $this->subscriber = new UpdateTweetLikesCountSubscriber($this->tweetRepository);

        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $event = new TweetWasUnliked($tweetId, $userId);

        $tweet = Tweet::create($userId, 'Content');
        $tweet->like();

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willReturn($tweet);

        $this->tweetRepository
            ->expects(self::once())
            ->method('add')
            ->with(
                self::callback(function (Tweet $savedTweet): bool {
                    return 0 === $savedTweet->likes();
                }),
            );

        $this->subscriber->onTweetUnliked($event);
    }

    #[Test]
    public function onTweetLikedDoesNotFailWhenTweetNotFound(): void
    {
        $this->tweetRepository = $this->createMock(TweetRepository::class);
        $this->subscriber = new UpdateTweetLikesCountSubscriber($this->tweetRepository);

        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $event = new TweetWasLiked($tweetId, 'some-user-id');

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willThrowException(new TweetNotFoundException($tweetId));

        $this->tweetRepository
            ->expects(self::never())
            ->method('add');

        $this->subscriber->onTweetLiked($event);
    }

    #[Test]
    public function onTweetUnlikedDoesNotFailWhenTweetNotFound(): void
    {
        // Arrange
        $this->tweetRepository = $this->createMock(TweetRepository::class);
        $this->subscriber = new UpdateTweetLikesCountSubscriber($this->tweetRepository);

        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $event = new TweetWasUnliked($tweetId, 'some-user-id');

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willThrowException(new TweetNotFoundException($tweetId));

        $this->tweetRepository
            ->expects(self::never())
            ->method('add');

        // Act
        $this->subscriber->onTweetUnliked($event);
    }

    #[Test]
    public function likesCountIsUpdatedCorrectlyWhenMultipleUsersLikeAndUnlike(): void
    {
        $this->tweetRepository = $this->createMock(TweetRepository::class);
        $this->subscriber = new UpdateTweetLikesCountSubscriber($this->tweetRepository);

        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $ownerId = '550e8400-e29b-41d4-a716-446655440000';
        $tweet = Tweet::create($ownerId, 'Manual test content');

        $this->tweetRepository
            ->expects(self::exactly(6))
            ->method('getById')
            ->with($tweetId)
            ->willReturn($tweet);

        $this->tweetRepository
            ->expects(self::exactly(6))
            ->method('add')
            ->with($tweet);

        $user1 = '019bbcb2-0e7d-7d69-9f4a-d6d091f2e2b4';
        $user2 = '019bbcb3-0a4b-7fe9-a4bf-2b10cc029841';
        $user3 = '019bbcb3-2785-76b4-9e91-fa36b07b407c';

        $this->subscriber->onTweetLiked(new TweetWasLiked($tweetId, $user1));
        self::assertSame(1, $tweet->likes());

        $this->subscriber->onTweetLiked(new TweetWasLiked($tweetId, $user2));
        self::assertSame(2, $tweet->likes());

        $this->subscriber->onTweetLiked(new TweetWasLiked($tweetId, $user3));
        self::assertSame(3, $tweet->likes());

        $this->subscriber->onTweetUnliked(new TweetWasUnliked($tweetId, $user1));
        self::assertSame(2, $tweet->likes());

        $this->subscriber->onTweetUnliked(new TweetWasUnliked($tweetId, $user2));
        self::assertSame(1, $tweet->likes());

        $this->subscriber->onTweetUnliked(new TweetWasUnliked($tweetId, $user3));
        self::assertSame(0, $tweet->likes());
    }
}
