<?php

namespace App\Http\Middleware;

use App\Modules\Infrastructure\Data\ServerNodeAssignment;
use App\Modules\Infrastructure\Services\LoadBalancerService;
use App\Modules\RequestLogs\Services\RequestLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AssignServerNodeMiddleware
{
    public function __construct(
        private readonly LoadBalancerService $loadBalancerService,
        private readonly RequestLogService $requestLogService,
    ) {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        try {
            $assignment = $this->loadBalancerService->acquireNode();
        } catch (\Throwable) {
            $assignment = null;
        }

        if ($assignment) {
            $request->attributes->set('server_node_assignment', $assignment);
        }

        try {
            $response = $next($request);
        } finally {
            $statusCode = isset($response) ? $response->getStatusCode() : 500;

            try {
                $this->requestLogService->createAutomaticRequestLog($request, $assignment, $startedAt, $statusCode);
            } catch (\Throwable) {
                // Request logging is observability only; SQLite lock pressure must not become an HTTP 500.
            }

            if ($assignment) {
                $this->loadBalancerService->releaseNode($assignment);
            }
        }

        return $this->withHeaders($response, $assignment);
    }

    private function withHeaders(Response $response, ?ServerNodeAssignment $assignment): Response
    {
        $response->headers->set('X-Load-Balancer-Strategy', config('load_balancer.strategy', 'least-loaded'));

        if ($assignment === null) {
            $response->headers->set('X-Server-Node', 'unassigned');

            return $response;
        }

        $response->headers->set('X-Server-Node-Id', (string) $assignment->nodeId);
        $response->headers->set('X-Server-Node', $assignment->nodeName);
        $response->headers->set('X-Server-Host', $assignment->host);
        $response->headers->set('X-Server-Current-Load', (string) $assignment->currentLoad);

        return $response;
    }
}
