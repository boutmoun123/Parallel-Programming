<?php

namespace App\Modules\RequestLogs\Services;

use App\Models\RequestLog;
use App\Modules\Infrastructure\Data\ServerNodeAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestLogService
{
    /**
     * @return Collection<int, RequestLog>
     */
    public function getLatestRequestLogs(): Collection
    {
        return RequestLog::query()
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRequestLog(array $data): RequestLog
    {
        $data['method'] = Str::upper($data['method']);

        return RequestLog::create($data);
    }

    public function createAutomaticRequestLog(
        Request $request,
        ?ServerNodeAssignment $assignment,
        float $startedAt,
        int $statusCode,
    ): RequestLog {
        return $this->createRequestLog([
            'server_node_id' => $assignment?->nodeId,
            'user_id' => $request->user()?->id,
            'operation_name' => $this->operationName($request),
            'endpoint' => '/'.ltrim($request->path(), '/'),
            'method' => $request->method(),
            'response_time_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'status_code' => $statusCode,
        ]);
    }

    private function operationName(Request $request): string
    {
        $actionName = $request->route()?->getActionName();

        if (is_string($actionName) && str_contains($actionName, '@')) {
            [$class, $method] = explode('@', $actionName, 2);

            return class_basename($class).'::'.$method;
        }

        return Str::upper($request->method()).' '.$request->path();
    }
}
