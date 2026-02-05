<?php

declare(strict_types=1);

namespace Twitter\Tests\Shared\Infrastructure\EventSubscriber;

use Assert\InvalidArgumentException as AssertInvalidArgumentException;
use Assert\LazyAssertionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twitter\Shared\Infrastructure\EventSubscriber\ExceptionSubscriber;
use Twitter\Tweet\Domain\Tweet\Exception\TweetNotFoundException;

#[Group('unit')]
#[CoversClass(ExceptionSubscriber::class)]
final class ExceptionSubscriberTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        // Arrange
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    #[Test]
    public function onKernelExceptionSkipsNonMainRequest(): void
    {
        // Arrange
        $subscriber = new ExceptionSubscriber($this->logger, environment: 'dev');
        $event = new ExceptionEvent(
            $this->kernel,
            Request::create('/api/test'),
            HttpKernelInterface::SUB_REQUEST,
            new RuntimeException('boom'),
        );

        $this->logger
            ->expects(self::never())
            ->method('error');

        // Act
        $subscriber->onKernelException($event);

        // Assert
        self::assertNull($event->getResponse());
    }

    #[Test]
    public function onKernelExceptionSkipsNonApiRoutes(): void
    {
        // Arrange
        $subscriber = new ExceptionSubscriber($this->logger, environment: 'dev');
        $event = new ExceptionEvent(
            $this->kernel,
            Request::create('/profile'),
            HttpKernelInterface::MAIN_REQUEST,
            new RuntimeException('boom'),
        );

        $this->logger
            ->expects(self::never())
            ->method('error');

        // Act
        $subscriber->onKernelException($event);

        // Assert
        self::assertNull($event->getResponse());
    }

    #[Test]
    public function onKernelExceptionMapsKnownDomainException(): void
    {
        // Arrange
        $subscriber = new ExceptionSubscriber($this->logger, environment: 'dev');
        $throwable = new TweetNotFoundException('019b5f3f-d110-7908-9177-5df439942a8b');
        $event = new ExceptionEvent(
            $this->kernel,
            Request::create('/api/tweets/1'),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Exception caught',
                self::callback(static function (array $context): bool {
                    return isset($context['exception'], $context['message'], $context['trace'])
                        && TweetNotFoundException::class === $context['exception'];
                }),
            );

        // Act
        $subscriber->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());

        $payload = json_decode($response->getContent() ?: '', true);
        self::assertSame('TWEET_NOT_FOUND', $payload['error']['code'] ?? null);
        self::assertSame('The tweet with this ID was not found', $payload['error']['message'] ?? null);
    }

    #[Test]
    public function onKernelExceptionReturnsValidationErrorsForInvalidArgumentException(): void
    {
        // Arrange
        $subscriber = new ExceptionSubscriber($this->logger);
        $throwable = new AssertInvalidArgumentException('Must not be blank', 0, 'content', '');
        $event = new ExceptionEvent(
            $this->kernel,
            Request::create('/api/tweets'),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );

        $this->logger
            ->expects(self::once())
            ->method('error');

        // Act
        $subscriber->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(422, $response->getStatusCode());

        $payload = json_decode($response->getContent() ?: '', true);
        self::assertSame('content', $payload['errors']['field'] ?? null);
        self::assertSame('Must not be blank', $payload['errors']['message'] ?? null);
    }

    #[Test]
    public function onKernelExceptionReturnsValidationErrorsForLazyAssertionException(): void
    {
        // Arrange
        $subscriber = new ExceptionSubscriber($this->logger);

        $errors = [
            new AssertInvalidArgumentException('Invalid email', 0, 'email', 'test@example'),
            new AssertInvalidArgumentException('Too short', 0, 'password', '123'),
        ];
        $throwable = LazyAssertionException::fromErrors($errors);

        $event = new ExceptionEvent(
            $this->kernel,
            Request::create('/api/register'),
            HttpKernelInterface::MAIN_REQUEST,
            $throwable,
        );

        $this->logger
            ->expects(self::once())
            ->method('error');

        // Act
        $subscriber->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(422, $response->getStatusCode());

        $payload = json_decode($response->getContent() ?: '', true);
        self::assertIsArray($payload['errors'] ?? null);
        self::assertCount(2, $payload['errors']);
        self::assertSame('email', $payload['errors'][0]['field'] ?? null);
        self::assertSame('password', $payload['errors'][1]['field'] ?? null);
    }

    #[Test]
    public function onKernelExceptionHandlesHttpExceptions(): void
    {
        // Arrange
        $subscriber = new ExceptionSubscriber($this->logger);
        $event = new ExceptionEvent(
            $this->kernel,
            Request::create('/api/anything'),
            HttpKernelInterface::MAIN_REQUEST,
            new NotFoundHttpException('not found'),
        );

        $this->logger
            ->expects(self::once())
            ->method('error');

        // Act
        $subscriber->onKernelException($event);

        // Assert
        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());

        $payload = json_decode($response->getContent() ?: '', true);
        self::assertSame('HTTP_ERROR', $payload['error']['code'] ?? null);
        self::assertSame('An error occurred', $payload['error']['message'] ?? null);
    }
}
