<?php

namespace App\Modules\BenchmarkResults\Controllers;

use App\Http\Controllers\Controller;
use App\Models\BenchmarkResult;
use App\Modules\BenchmarkResults\Requests\StoreBenchmarkResultRequest;
use App\Modules\BenchmarkResults\Resources\BenchmarkResultResource;
use App\Modules\BenchmarkResults\Services\BenchmarkResultService;
use Illuminate\Http\JsonResponse;

class BenchmarkResultController extends Controller
{
    public function __construct(private readonly BenchmarkResultService $benchmarkResultService)
    {
    }

    public function index(): JsonResponse
    {
        $benchmarkResults = BenchmarkResult::query()
            ->latest()
            ->get();

        return $this->success('Benchmark results retrieved successfully', BenchmarkResultResource::collection($benchmarkResults));
    }

    public function store(StoreBenchmarkResultRequest $request): JsonResponse
    {
        $benchmarkResult = $this->benchmarkResultService->createBenchmarkResult($request->validated());

        return $this->success('Benchmark result created successfully', new BenchmarkResultResource($benchmarkResult), 201);
    }

    public function show(BenchmarkResult $benchmarkResult): JsonResponse
    {
        return $this->success('Benchmark result retrieved successfully', new BenchmarkResultResource($benchmarkResult));
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
