<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Application\UseCase\UpdateTweet;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\UseCase\UpdateTweet\UpdateTweetCommand;
use Twitter\Tweet\Application\UseCase\UpdateTweet\UpdateTweetCommandHandler;
use Twitter\Tweet\Domain\Tweet\Exception\TweetAccessDeniedException;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

#[Group('unit')]
#[CoversMethod(UpdateTweetCommandHandler::class, 'handle')]
final class UpdateTweetCommandHandlerTest extends TestCase
{
    private UpdateTweetCommandHandler $handler;
    private MockObject|TweetRepository $tweetRepository;

    protected function setUp(): void
    {
        $this->tweetRepository = $this->createMock(TweetRepository::class);
        $this->handler = new UpdateTweetCommandHandler($this->tweetRepository);
    }

    /**
     * @throws TweetAccessDeniedException
     * @throws TweetNotFoundException
     */
    #[Test]
    public function updatesTweetForOwner(): void
    {
        $tweet = Tweet::create(
            '123e4567-e89b-12d3-a456-426614174000',
            'Old content',
        );

        $tweetId = $tweet->id();

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willReturn($tweet);

        $this->tweetRepository
            ->expects(self::once())
            ->method('flush');

        $command = new UpdateTweetCommand(
            '123e4567-e89b-12d3-a456-426614174000',
            $tweetId,
            'New content',
        );

        $result = $this->handler->handle($command);

        self::assertSame('New content', $result->content);
        self::assertSame('New content', $tweet->content());
        self::assertInstanceOf(DateTimeImmutable::class, $result->updatedAt);
    }

    /**
     * @throws TweetNotFoundException
     */
    #[Test]
    public function throwsExceptionWhenTweetIsUpdatedNotByTheOwner(): void
    {
        $tweet = Tweet::create(
            '123e4567-e89b-12d3-a456-426614174000',
            'Content',
        );

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->willReturn($tweet);

        $this->tweetRepository
            ->expects(self::never())
            ->method('flush');

        $command = new UpdateTweetCommand(
            'definitely-not-the-owner-id',
            $tweet->id(),
            'New content',
        );

        $this->expectException(TweetAccessDeniedException::class);

        $this->handler->handle($command);
    }

    /**
     * @throws TweetAccessDeniedException
     */
    #[Test]
    public function throwsExceptionWhenTweetDoesNotExist(): void
    {
        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->willThrowException(new TweetNotFoundException('non-existent-id'));

        $this->tweetRepository
            ->expects(self::never())
            ->method('flush');

        $command = new UpdateTweetCommand(
            '123e4567-e89b-12d3-a456-426614174000',
            'non-existent-id',
            'Content',
        );

        $this->expectException(TweetNotFoundException::class);

        $this->handler->handle($command);
    }
}
