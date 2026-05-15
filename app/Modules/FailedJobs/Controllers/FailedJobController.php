<?php

namespace App\Modules\FailedJobs\Controllers;

use App\Http\Controllers\Controller;
use App\Models\FailedJob;
use App\Modules\FailedJobs\Resources\FailedJobResource;
use App\Modules\FailedJobs\Services\FailedJobService;
use Illuminate\Http\JsonResponse;

class FailedJobController extends Controller
{
    public function __construct(private readonly FailedJobService $failedJobService)
    {
    }

    public function index(): JsonResponse
    {
        $failedJobs = $this->failedJobService->getLatestFailedJobs();

        return $this->success('Failed jobs retrieved successfully', FailedJobResource::collection($failedJobs));
    }

    public function show(FailedJob $failedJob): JsonResponse
    {
        $failedJob = $this->failedJobService->getFailedJobById($failedJob);

        return $this->success('Failed job retrieved successfully', new FailedJobResource($failedJob));
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
