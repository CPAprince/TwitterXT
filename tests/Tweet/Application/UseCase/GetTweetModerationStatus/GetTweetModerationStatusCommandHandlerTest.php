<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Application\UseCase\GetTweetModerationStatus;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\UseCase\GetTweetModerationStatus\GetTweetModerationStatusCommand;
use Twitter\Tweet\Application\UseCase\GetTweetModerationStatus\GetTweetModerationStatusCommandHandler;
use Twitter\Tweet\Domain\Tweet\Exception\TweetAccessDeniedException;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

#[Group('unit')]
#[CoversClass(GetTweetModerationStatusCommandHandler::class)]
final class GetTweetModerationStatusCommandHandlerTest extends TestCase
{
    private TweetRepository&MockObject $tweetRepository;
    private GetTweetModerationStatusCommandHandler $handler;

    protected function setUp(): void
    {
        $this->tweetRepository = $this->createMock(TweetRepository::class);
        $this->handler = new GetTweetModerationStatusCommandHandler($this->tweetRepository);
    }

    #[Test]
    public function handleReturnsPendingStatusForPendingTweet(): void
    {
        $authorId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $tweet = Tweet::create($authorId, 'Pending tweet');
        $tweetId = $tweet->id();

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willReturn($tweet);

        $result = $this->handler->handle(new GetTweetModerationStatusCommand($tweetId, $authorId));

        self::assertSame($tweetId, $result->tweetId);
        self::assertSame('pending', $result->status);
    }

    #[Test]
    public function handleReturnsApprovedStatusForApprovedTweet(): void
    {
        $authorId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $tweet = Tweet::create($authorId, 'Approved tweet');
        $tweet->approveModeration();
        $tweetId = $tweet->id();

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willReturn($tweet);

        $result = $this->handler->handle(new GetTweetModerationStatusCommand($tweetId, $authorId));

        self::assertSame($tweetId, $result->tweetId);
        self::assertSame('approved', $result->status);
    }

    #[Test]
    public function handleReturnsRejectedStatusForRejectedTweet(): void
    {
        $authorId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $tweet = Tweet::create($authorId, 'Rejected tweet');
        $tweet->rejectModeration();
        $tweetId = $tweet->id();

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willReturn($tweet);

        $result = $this->handler->handle(new GetTweetModerationStatusCommand($tweetId, $authorId));

        self::assertSame($tweetId, $result->tweetId);
        self::assertSame('rejected', $result->status);
    }

    #[Test]
    public function handleThrowsAccessDeniedExceptionWhenUserIsNotAuthor(): void
    {
        $authorId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $otherUserId = '019b5f3f-d110-7908-9177-5df439942999';
        $tweet = Tweet::create($authorId, 'Some tweet');
        $tweetId = $tweet->id();

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willReturn($tweet);

        $this->expectException(TweetAccessDeniedException::class);

        $this->handler->handle(new GetTweetModerationStatusCommand($tweetId, $otherUserId));
    }

    #[Test]
    public function handlePropagatesTweetNotFoundExceptionWhenTweetDoesNotExist(): void
    {
        $tweetId = '019b5f41-0e5b-7f65-8b7a-0f9c0b3b3c11';
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willThrowException(new TweetNotFoundException($tweetId));

        $this->expectException(TweetNotFoundException::class);

        $this->handler->handle(new GetTweetModerationStatusCommand($tweetId, $userId));
    }
}
