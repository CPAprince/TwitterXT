<?php

declare(strict_types=1);

namespace Twitter\HealthCheck\Controller;

use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/health', name: 'api_health_check', methods: ['GET'])]
final class HealthCheckController
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'checkedAt' => new DateTimeImmutable()->format(DATE_RFC3339),
        ]);
    }
}
