<?php

declare(strict_types=1);

namespace Twitter\Tests\Realtime\Infrastructure\EventSubscriber;

use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twitter\Like\Domain\Like\Event\TweetWasLiked;
use Twitter\Like\Domain\Like\Event\TweetWasUnliked;
use Twitter\Realtime\Infrastructure\EventSubscriber\LikeBroadcastSubscriber;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;
use Twitter\Tweet\Domain\Tweet\Model\Tweet;
use Twitter\Tweet\Domain\Tweet\Model\TweetRepository;

#[Group('unit')]
#[CoversClass(LikeBroadcastSubscriber::class)]
final class LikeBroadcastSubscriberTest extends TestCase
{
    private HubInterface $hub;
    private TweetRepository $tweetRepository;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        // Arrange
        $this->hub = $this->createStub(HubInterface::class);
        $this->tweetRepository = $this->createStub(TweetRepository::class);
        $this->logger = $this->createStub(LoggerInterface::class);
    }

    #[Test]
    public function getSubscribedEventsReturnsCorrectMapping(): void
    {
        // Act
        $events = LikeBroadcastSubscriber::getSubscribedEvents();

        // Assert
        self::assertSame([
            TweetWasLiked::class => ['onLikeChanged', -10],
            TweetWasUnliked::class => ['onLikeChanged', -10],
        ], $events);
    }

    #[Test]
    public function onLikeChangedPublishesUpdateForTweetWasLiked(): void
    {
        // Arrange
        $this->hub = $this->createMock(HubInterface::class);
        $this->tweetRepository = $this->createMock(TweetRepository::class);

        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $event = new TweetWasLiked($tweetId, $userId);

        $tweet = Tweet::create($userId, 'Hello');
        $tweet->like();
        $tweet->like();

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willReturn($tweet);

        $this->hub
            ->expects(self::once())
            ->method('publish')
            ->with(self::callback(function (Update $update) use ($tweetId, $userId): bool {
                self::assertSame(['https://example.com/tweets/likes'], $update->getTopics());

                $payload = json_decode($update->getData(), true);
                self::assertIsArray($payload);

                self::assertSame($tweetId, $payload['tweetId'] ?? null);
                self::assertSame(2, $payload['likesCount'] ?? null);
                self::assertSame($userId, $payload['triggeredBy'] ?? null);

                return true;
            }))
            ->willReturn('update-id');

        $subscriber = new LikeBroadcastSubscriber(
            hub: $this->hub,
            tweetRepository: $this->tweetRepository,
            topicBaseUrl: 'https://example.com:443',
            logger: $this->logger,
        );

        // Act
        $subscriber->onLikeChanged($event);
    }

    #[Test]
    public function onLikeChangedPublishesUpdateForTweetWasUnliked(): void
    {
        // Arrange
        $this->hub = $this->createMock(HubInterface::class);
        $this->tweetRepository = $this->createMock(TweetRepository::class);

        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $event = new TweetWasUnliked($tweetId, $userId);

        $tweet = Tweet::create($userId, 'Hello');
        $tweet->like();

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willReturn($tweet);

        $this->hub
            ->expects(self::once())
            ->method('publish')
            ->with(self::isInstanceOf(Update::class))
            ->willReturn('update-id');

        $subscriber = new LikeBroadcastSubscriber(
            hub: $this->hub,
            tweetRepository: $this->tweetRepository,
            topicBaseUrl: 'https://example.com',
            logger: $this->logger,
        );

        // Act
        $subscriber->onLikeChanged($event);
    }

    #[Test]
    public function onLikeChangedReturnsEarlyWhenTweetNotFound(): void
    {
        // Arrange
        $this->hub = $this->createMock(HubInterface::class);
        $this->tweetRepository = $this->createMock(TweetRepository::class);

        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $event = new TweetWasLiked($tweetId, '550e8400-e29b-41d4-a716-446655440000');

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willThrowException(new TweetNotFoundException($tweetId));

        $this->hub
            ->expects(self::never())
            ->method('publish');

        $subscriber = new LikeBroadcastSubscriber(
            hub: $this->hub,
            tweetRepository: $this->tweetRepository,
            topicBaseUrl: 'https://example.com',
            logger: $this->logger,
        );

        // Act
        $subscriber->onLikeChanged($event);
    }

    #[Test]
    public function onLikeChangedLogsWarningWhenHubPublishFails(): void
    {
        // Arrange
        $this->hub = $this->createMock(HubInterface::class);
        $this->tweetRepository = $this->createMock(TweetRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $event = new TweetWasLiked($tweetId, $userId);

        $tweet = Tweet::create($userId, 'Hello');

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willReturn($tweet);

        $this->hub
            ->expects(self::once())
            ->method('publish')
            ->willThrowException(new RuntimeException('hub down'));

        $this->logger
            ->expects(self::once())
            ->method('warning')
            ->with(
                'Failed to broadcast like update',
                self::callback(static function (array $context) use ($tweetId): bool {
                    return ($context['tweetId'] ?? null) === $tweetId
                        && ($context['error'] ?? null) === 'hub down';
                }),
            );

        $subscriber = new LikeBroadcastSubscriber(
            hub: $this->hub,
            tweetRepository: $this->tweetRepository,
            topicBaseUrl: 'https://example.com',
            logger: $this->logger,
        );

        // Act
        $subscriber->onLikeChanged($event);
    }

    #[Test]
    #[DataProvider('topicNormalizationProvider')]
    public function onLikeChangedNormalizesUrlByStrippingDefaultPorts(string $topicBaseUrl, string $expectedTopic): void
    {
        // Arrange
        $this->hub = $this->createMock(HubInterface::class);
        $this->tweetRepository = $this->createMock(TweetRepository::class);

        $tweetId = '019b5f3f-d110-7908-9177-5df439942a8b';
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $event = new TweetWasLiked($tweetId, $userId);

        $tweet = Tweet::create($userId, 'Hello');

        $this->tweetRepository
            ->expects(self::once())
            ->method('getById')
            ->with($tweetId)
            ->willReturn($tweet);

        $this->hub
            ->expects(self::once())
            ->method('publish')
            ->with(self::callback(function (Update $update) use ($expectedTopic): bool {
                self::assertSame([$expectedTopic], $update->getTopics());

                return true;
            }))
            ->willReturn('update-id');

        $subscriber = new LikeBroadcastSubscriber(
            hub: $this->hub,
            tweetRepository: $this->tweetRepository,
            topicBaseUrl: $topicBaseUrl,
            logger: $this->logger,
        );

        // Act
        $subscriber->onLikeChanged($event);
    }

    public static function topicNormalizationProvider(): Generator
    {
        yield 'https default port is stripped' => ['https://example.com:443', 'https://example.com/tweets/likes'];
        yield 'http default port is stripped' => ['http://example.com:80', 'http://example.com/tweets/likes'];
        yield 'custom https port is kept' => ['https://example.com:8080', 'https://example.com:8080/tweets/likes'];
        yield 'no port stays as-is' => ['https://example.com', 'https://example.com/tweets/likes'];
    }
}
