<?php

declare(strict_types=1);

namespace Twitter\Shared\Infrastructure\RateLimiter;

use LogicException;
use Override;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class RateLimitSubscriber implements EventSubscriberInterface
{
    public const string FALLBACK_KEY = 'anonymous';

    public function __construct(
        private Security $security,
        #[AutowireLocator('rate_limiter', indexAttribute: 'name')]
        private ContainerInterface $limiters,
    ) {}

    #[Override]
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        if (is_array($controller)) {
            [$instance, $method] = $controller;
            $reflectionClass = new ReflectionClass($instance);
            $reflectionMethod = new ReflectionMethod($instance, $method);
        } elseif (is_object($controller) && method_exists($controller, '__invoke')) {
            $reflectionClass = new ReflectionClass($controller);
            $reflectionMethod = $reflectionClass->getMethod('__invoke');
        } else {
            return;
        }

        $attributes = [
            ...$reflectionClass->getAttributes(RateLimited::class),
            ...$reflectionMethod->getAttributes(RateLimited::class),
        ];

        if (empty($attributes)) {
            return;
        }

        $request = $event->getRequest();
        $user = $this->security->getUser();

        $key = $user?->getUserIdentifier() ?? $request->getClientIp() ?? self::FALLBACK_KEY;

        foreach ($attributes as $attribute) {
            /** @var RateLimited $config */
            $config = $attribute->newInstance();

            if (!$this->limiters->has($config->target)) {
                throw new LogicException(sprintf('Rate limiter "%s" is not defined.', $config->target));
            }

            /** @var RateLimiterFactory $factory */
            $factory = $this->limiters->get($config->target);

            $limit = $factory->create($key)->consume();

            if (!$limit->isAccepted()) {
                throw new TooManyRequestsHttpException(max(0, $limit->getRetryAfter()->getTimestamp() - time()));
            }
        }
    }
}
