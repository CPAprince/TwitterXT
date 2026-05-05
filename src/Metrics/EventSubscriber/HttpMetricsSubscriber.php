<?php

declare(strict_types=1);

namespace Twitter\Metrics\EventSubscriber;

use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Histogram;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class HttpMetricsSubscriber implements EventSubscriberInterface
{
    private const START_ATTR = '_metrics_start_time';

    private Counter $requestsTotal;
    private Histogram $latency;
    private Counter $errorsTotal;
    private Counter $rateLimitedTotal;

    public function __construct(
        private readonly CollectorRegistry $registry,
    ) {
        $this->requestsTotal = $this->registry->getOrRegisterCounter(
            'http',
            'requests_total',
            'Total HTTP requests',
            ['route', 'method', 'status']
        );

        $this->latency = $this->registry->getOrRegisterHistogram(
            'http',
            'request_duration_seconds',
            'HTTP request latency',
            ['route'],
            [0.001, 0.002, 0.003, 0.005, 0.0075, 0.01, 0.0125, 0.015, 0.0175, 0.020, 0.025, 0.03, 0.035, 0.04, 0.045, 0.05, 0.075, 0.1, 0.15, 0.2, 0.3, 0.4, 0.5, 0.75, 1, 2, 3, 5]
        );

        $this->errorsTotal = $this->registry->getOrRegisterCounter(
            'http',
            'requests_errors_total',
            'HTTP 5xx errors',
            ['route']
        );

        $this->rateLimitedTotal = $this->registry->getOrRegisterCounter(
            'http',
            'rate_limited_total',
            'HTTP 429 (rate-limited) responses',
            ['route', 'method']
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onRequest', 100],
            KernelEvents::RESPONSE => ['onResponse', -100],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        $route = (string) $request->attributes->get('_route', 'unknown');
        if ('metrics' === $route) {
            return;
        }

        // Safe per-request storage (no races)
        $request->attributes->set(self::START_ATTR, microtime(true));
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        $route = (string) $request->attributes->get('_route', 'unknown');
        if ('metrics' === $route) {
            return;
        }

        $method = (string) $request->getMethod();
        $statusCode = (int) $response->getStatusCode();
        $status = (string) $statusCode;

        $start = $request->attributes->get(self::START_ATTR);
        $duration = is_float($start) ? (microtime(true) - $start) : 0.0;
        if ($duration < 0) {
            $duration = 0.0;
        }

        // total requests
        $this->requestsTotal->inc([$route, $method, $status]);

        // latency histogram
        $this->latency->observe($duration, [$route]);

        // error counter
        if ($statusCode >= 500) {
            $this->errorsTotal->inc([$route]);
        }

        // rate-limit counter
        if (429 === $statusCode) {
            $this->rateLimitedTotal->inc([$route, $method]);
        }
    }
}
