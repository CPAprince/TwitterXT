<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Application\UseCase\GetUserTweets;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Application\UseCase\GetUserTweets\GetUserTweetsQuery;
use Twitter\Tweet\Application\UseCase\GetUserTweets\GetUserTweetsQueryHandler;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

#[Group('unit')]
#[CoversMethod(GetUserTweetsQueryHandler::class, 'handle')]
final class GetUserTweetsQueryHandlerTest extends TestCase
{
    private GetUserTweetsQueryHandler $handler;
    private MockObject|TweetRepository $tweetRepository;

    protected function setUp(): void
    {
        $this->tweetRepository = $this->createMock(TweetRepository::class);
        $this->handler = new GetUserTweetsQueryHandler($this->tweetRepository);
    }

    #[Test]
    public function returnsEmptyListWhenNoTweetsExist(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $this->tweetRepository
            ->expects(self::once())
            ->method('getUserTweets')
            ->willReturn([]);

        $query = new GetUserTweetsQuery($userId, limit: 10, page: 1);
        $result = $this->handler->handle($query);

        self::assertSame([], $result->tweets);
    }

    #[Test]
    public function returnsAllTweetsWhenLimitIsZero(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $tweet1 = Tweet::create($userId, 'Tweet 1');
        $tweet2 = Tweet::create($userId, 'Tweet 2');
        $tweet3 = Tweet::create($userId, 'Tweet 3');

        $this->tweetRepository
            ->expects(self::once())
            ->method('getUserTweets')
            ->willReturn([$tweet1, $tweet2, $tweet3]);

        $query = new GetUserTweetsQuery($userId, limit: 0, page: 0);
        $result = $this->handler->handle($query);

        self::assertCount(3, $result->tweets);
    }

    #[Test]
    public function returnsAllTweetsWhenPageIsNegative(): void
    {
        $userId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $tweet1 = Tweet::create($userId, 'Tweet 1');
        $tweet2 = Tweet::create($userId, 'Tweet 2');

        $this->tweetRepository
            ->expects(self::once())
            ->method('getUserTweets')
            ->willReturn([$tweet1, $tweet2]);

        $query = new GetUserTweetsQuery($userId, limit: 10, page: -1);
        $result = $this->handler->handle($query);

        self::assertCount(2, $result->tweets);
    }
}
