<?php

namespace App\Http\Middleware;

use App\Modules\Infrastructure\Data\CapacityReservation;
use App\Modules\Infrastructure\Services\CapacityService;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CapacityControlMiddleware
{
    public function __construct(private readonly CapacityService $capacityService)
    {
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $group = 'critical-operations'): Response
    {
        try {
            $reservation = $this->capacityService->acquire($group);
        } catch (LockTimeoutException) {
            return $this->unavailableResponse($group);
        }

        if ($reservation === null) {
            return $this->atCapacityResponse($group);
        }

        try {
            $response = $next($request);
        } finally {
            try {
                $this->capacityService->release($reservation);
            } catch (LockTimeoutException) {
                // Expired reservations are pruned automatically on the next acquire.
            }
        }

        return $this->withHeaders($response, $reservation);
    }

    private function unavailableResponse(string $group): JsonResponse
    {
        $snapshot = $this->safeSnapshot($group);

        return response()->json([
            'success' => false,
            'message' => 'Capacity controller is temporarily unavailable.',
            'data' => null,
            'errors' => [
                'capacity' => ['Please retry the operation in a moment.'],
            ],
            'meta' => $snapshot,
        ], 503, [
            'Retry-After' => (string) $snapshot['retry_after_seconds'],
        ]);
    }

    private function atCapacityResponse(string $group): JsonResponse
    {
        $snapshot = $this->safeSnapshot($group);

        return response()->json([
            'success' => false,
            'message' => 'System is temporarily at capacity for this operation.',
            'data' => null,
            'errors' => [
                'capacity' => ['Please retry after one of the active operations completes.'],
            ],
            'meta' => $snapshot,
        ], 503, [
            'Retry-After' => (string) $snapshot['retry_after_seconds'],
        ]);
    }

    private function withHeaders(Response $response, CapacityReservation $reservation): Response
    {
        $snapshot = $this->safeSnapshot($reservation->group, $reservation);

        $response->headers->set('X-Capacity-Group', $reservation->group);
        $response->headers->set('X-Capacity-Limit', (string) $snapshot['limit']);
        $response->headers->set('X-Capacity-Active', (string) $snapshot['active']);
        $response->headers->set('X-Capacity-Remaining', (string) $snapshot['remaining']);

        return $response;
    }

    /**
     * @return array{group: string, limit: int, active: int, remaining: int, retry_after_seconds: int}
     */
    private function safeSnapshot(string $group, ?CapacityReservation $reservation = null): array
    {
        try {
            return $this->capacityService->snapshot($group);
        } catch (LockTimeoutException) {
            $limit = (int) (config("capacity.groups.{$group}.limit") ?? config('capacity.default_limit', 10));
            $active = $reservation?->activeCount ?? $limit;
            $retryAfterSeconds = $reservation?->retryAfterSeconds
                ?? (int) (config("capacity.groups.{$group}.retry_after_seconds") ?? config('capacity.retry_after_seconds', 2));

            return [
                'group' => $group,
                'limit' => $limit,
                'active' => $active,
                'remaining' => max(0, $limit - $active),
                'retry_after_seconds' => $retryAfterSeconds,
            ];
        }
    }
}
