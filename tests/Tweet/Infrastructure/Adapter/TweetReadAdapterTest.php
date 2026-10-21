<?php

declare(strict_types=1);

namespace Twitter\Tests\Tweet\Infrastructure\Adapter;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Infrastructure\Adapter\TweetReadAdapter;

#[Group('unit')]
#[CoversClass(TweetReadAdapter::class)]
final class TweetReadAdapterTest extends TestCase
{
    private Connection&MockObject $connection;
    private TweetReadAdapter $adapter;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->adapter = new TweetReadAdapter($this->connection);
    }

    #[Test]
    public function returnsTrueWhenTweetIsApproved(): void
    {
        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn(Tweet::MODERATION_APPROVED);

        self::assertTrue($this->adapter->isApproved($tweetId));
    }

    #[Test]
    public function returnsFalseWhenTweetIsPending(): void
    {
        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn(Tweet::MODERATION_PENDING);

        self::assertFalse($this->adapter->isApproved($tweetId));
    }

    #[Test]
    public function returnsFalseWhenTweetIsRejected(): void
    {
        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn(Tweet::MODERATION_REJECTED);

        self::assertFalse($this->adapter->isApproved($tweetId));
    }

    #[Test]
    public function returnsFalseWhenTweetDoesNotExist(): void
    {
        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';

        $this->connection
            ->expects(self::once())
            ->method('fetchOne')
            ->willReturn(false);

        self::assertFalse($this->adapter->isApproved($tweetId));
    }
}
