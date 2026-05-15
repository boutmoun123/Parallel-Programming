<?php

namespace App\Modules\RequestLogs\Controllers;

use App\Http\Controllers\Controller;
use App\Models\RequestLog;
use App\Modules\RequestLogs\Requests\StoreRequestLogRequest;
use App\Modules\RequestLogs\Resources\RequestLogResource;
use App\Modules\RequestLogs\Services\RequestLogService;
use Illuminate\Http\JsonResponse;

class RequestLogController extends Controller
{
    public function __construct(private readonly RequestLogService $requestLogService)
    {
    }

    public function index(): JsonResponse
    {
        $requestLogs = $this->requestLogService->getLatestRequestLogs();

        return $this->success('Request logs retrieved successfully', RequestLogResource::collection($requestLogs));
    }

    public function store(StoreRequestLogRequest $request): JsonResponse
    {
        $requestLog = $this->requestLogService->createRequestLog($request->validated());

        return $this->success('Request log created successfully', new RequestLogResource($requestLog), 201);
    }

    public function show(RequestLog $requestLog): JsonResponse
    {
        return $this->success('Request log retrieved successfully', new RequestLogResource($requestLog));
    }

    private function success(string $message, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }
}
