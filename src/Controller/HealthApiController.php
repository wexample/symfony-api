<?php

namespace Wexample\SymfonyApi\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyApi\Api\Controller\AbstractApiController;
use Wexample\SymfonyApi\Service\HealthCheckService;

/**
 * For a load balancer, an orchestrator or an uptime monitor: 200 when every
 * probe passes, 503 otherwise. Routed only when the application imports
 * `routes_health.yaml`.
 */
final class HealthApiController extends AbstractApiController
{
    final public const string ROUTE_HEALTH = 'api_health';

    #[Route(path: '/api/health', name: self::ROUTE_HEALTH, methods: ['GET'])]
    public function health(HealthCheckService $healthCheckService): JsonResponse
    {
        $report = $healthCheckService->run();

        $response = (
            HealthCheckService::STATUS_OK === $report['status']
            ? self::apiResponseSuccess(data: $report)
            : self::apiResponseError('Unhealthy.', $report, code: Response::HTTP_SERVICE_UNAVAILABLE)
        )->toJsonResponse();

        // A cached answer would report a state that is no longer true.
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
