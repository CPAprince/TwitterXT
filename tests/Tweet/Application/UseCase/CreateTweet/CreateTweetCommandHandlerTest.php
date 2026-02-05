<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Application\UseCase\CreateTweet;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\UseCase\CreateTweet\CreateTweetCommand;
use Twitter\Tweet\Application\UseCase\CreateTweet\CreateTweetCommandHandler;
use Twitter\Tweet\Domain\Tweet\Exception\UserNotFoundException;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

#[Group('unit')]
#[CoversMethod(CreateTweetCommandHandler::class, 'handle')]
final class CreateTweetCommandHandlerTest extends TestCase
{
    private CreateTweetCommandHandler $handler;
    private MockObject|TweetRepository $tweetRepository;

    protected function setUp(): void
    {
        $this->tweetRepository = $this->createMock(TweetRepository::class);
        $this->handler = new CreateTweetCommandHandler($this->tweetRepository);
    }

    #[Test]
    public function handleCreatesTweetWithProvidedData(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $content = 'Hello, Twitter';

        $command = new CreateTweetCommand(
            userId: $userId,
            content: $content
        );

        $this->tweetRepository
            ->expects(self::once())
            ->method('add')
            ->with(self::callback(
                fn (Tweet $tweet): bool => $tweet->userId() === $userId
                    && $tweet->content() === $content
                    && '' !== $tweet->id()
            ));

        $result = $this->handler->handle($command);

        self::assertNotEmpty($result->tweetId);
    }

    #[Test]
    #[AllowMockObjectsWithoutExpectations]
    public function handlePropagatesUserNotFoundException(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $content = 'Hello, Twitter';

        $command = new CreateTweetCommand(
            userId: $userId,
            content: $content
        );

        $this->tweetRepository
            ->method('add')
            ->willThrowException(new UserNotFoundException($userId));

        $this->expectException(UserNotFoundException::class);

        $this->handler->handle($command);
    }
}
