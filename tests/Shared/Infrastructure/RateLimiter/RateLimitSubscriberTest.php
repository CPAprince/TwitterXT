<?php

declare(strict_types=1);

namespace Twitter\Tests\Shared\Infrastructure\RateLimiter;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Twitter\Shared\Infrastructure\RateLimiter\RateLimited;
use Twitter\Shared\Infrastructure\RateLimiter\RateLimitSubscriber;

#[Group('unit')]
#[CoversClass(RateLimitSubscriber::class)]
final class RateLimitSubscriberTest extends TestCase
{
    private Security&Stub $security;
    private ContainerInterface&MockObject $limiters;
    private RateLimitSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->security = $this->createStub(Security::class);
        $this->limiters = $this->createMock(ContainerInterface::class);
        $this->subscriber = new RateLimitSubscriber($this->security, $this->limiters);
    }

    #[Test]
    public function onKernelControllerSkipsIfNoAttribute(): void
    {
        $controller = new readonly class {
            public function __invoke(): array
            {
                return [];
            }
        };
        $event = $this->createControllerEvent($controller, Request::create('/api/test'));

        $this->limiters->expects(self::never())->method('has');

        $this->subscriber->onKernelController($event);
    }

    #[Test]
    public function onKernelControllerProcessesWithRateLimitedAttributeAndUserIdentifier(): void
    {
        $event = $this->createControllerEvent($this->createRateLimitedController(), Request::create('/api/test'));

        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user-123');
        $this->security->method('getUser')->willReturn($user);

        $this->setupLimiterMock('user-123', true);

        $this->subscriber->onKernelController($event);
    }

    #[Test]
    public function onKernelControllerUsesIpAddressIfUserNotAuthenticated(): void
    {
        $request = Request::create('/api/test', server: ['REMOTE_ADDR' => '192.168.1.1']);
        $event = $this->createControllerEvent($this->createRateLimitedController(), $request);

        $this->security->method('getUser')->willReturn(null);

        $this->setupLimiterMock('192.168.1.1', true);

        $this->subscriber->onKernelController($event);
    }

    #[Test]
    public function onKernelControllerUsesFallbackKeyIfUserNotAuthenticatedAndNoIp(): void
    {
        $request = $this->createStub(Request::class);
        $request->method('getClientIp')->willReturn(null);

        $event = $this->createControllerEvent($this->createRateLimitedController(), $request);

        $this->security->method('getUser')->willReturn(null);

        $this->setupLimiterMock(RateLimitSubscriber::FALLBACK_KEY, true);

        $this->subscriber->onKernelController($event);
    }

    #[Test]
    public function onKernelControllerThrowsTooManyRequestsExceptionWhenLimitExceeded(): void
    {
        $event = $this->createControllerEvent($this->createRateLimitedController(), Request::create('/api/test'));

        $user = $this->createStub(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('user-blocked');
        $this->security->method('getUser')->willReturn($user);

        $this->setupLimiterMock('user-blocked', false);

        $this->expectException(TooManyRequestsHttpException::class);

        $this->subscriber->onKernelController($event);
    }

    private function createRateLimitedController(): object
    {
        return new #[RateLimited('test_limiter')] readonly class {
            public function __invoke(): array
            {
                return [];
            }
        };
    }

    private function createControllerEvent(object $controller, Request $request): ControllerEvent
    {
        return new ControllerEvent(
            $this->createStub(HttpKernelInterface::class),
            $controller,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function setupLimiterMock(string $expectedKey, bool $isAccepted): void
    {
        $factory = $this->createMock(RateLimiterFactoryInterface::class);

        $this->limiters->method('has')->with('test_limiter')->willReturn(true);
        $this->limiters->method('get')->with('test_limiter')->willReturn($factory);

        $rateLimit = new RateLimit(
            $isAccepted ? 10 : 0,
            new DateTimeImmutable(),
            $isAccepted,
            10
        );

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($rateLimit);

        $factory->expects(self::once())
            ->method('create')
            ->with($expectedKey)
            ->willReturn($limiter);
    }
}
