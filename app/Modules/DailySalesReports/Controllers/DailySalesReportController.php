<?php

namespace App\Modules\DailySalesReports\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DailySalesReport;
use App\Modules\DailySalesReports\Requests\QueueDailySalesReportRequest;
use App\Modules\DailySalesReports\Requests\StoreDailySalesReportRequest;
use App\Modules\DailySalesReports\Resources\DailySalesReportResource;
use App\Modules\DailySalesReports\Services\DailySalesReportBatchService;
use App\Modules\DailySalesReports\Services\DailySalesReportService;
use Illuminate\Http\JsonResponse;

class DailySalesReportController extends Controller
{
    public function __construct(
        private readonly DailySalesReportService $dailySalesReportService,
        private readonly DailySalesReportBatchService $dailySalesReportBatchService,
    ) {
    }

    public function index(): JsonResponse
    {
        $startedAt = microtime(true);

        $result = $this->dailySalesReportService->getLatestDailySalesReports();

        return $this->success(
            'Daily sales reports retrieved successfully',
            DailySalesReportResource::collection($result['data'])->resolve(),
            $this->meta($result['source'], $startedAt)
        );
    }

    public function store(StoreDailySalesReportRequest $request): JsonResponse
    {
        $startedAt = microtime(true);

        $dailySalesReport = $this->dailySalesReportService->createDailySalesReport(
            $request->validated()
        );

        return $this->success(
            'Daily sales report created successfully',
            (new DailySalesReportResource($dailySalesReport))->resolve(),
            $this->meta('database', $startedAt),
            201
        );
    }

    public function show(DailySalesReport $dailySalesReport): JsonResponse
    {
        $startedAt = microtime(true);

        return $this->success(
            'Daily sales report retrieved successfully',
            (new DailySalesReportResource($dailySalesReport))->resolve(),
            $this->meta('database', $startedAt)
        );
    }

    public function queueGenerate(QueueDailySalesReportRequest $request): JsonResponse
    {
        $startedAt = microtime(true);

        $reportDate = $request->validated('report_date');

        $this->dailySalesReportBatchService->queueForDate($reportDate);

        return $this->success(
            'Daily sales report generation queued successfully',
            [
                'report_date' => $reportDate,
                'status' => 'queued',
            ],
            $this->meta('database', $startedAt),
            202
        );
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function success(
        string $message,
        mixed $data,
        array $meta,
        int $status = 200
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'errors' => null,
        ], $status);
    }

    /**
     * @return array{source: string, response_time_ms: float}
     */
    private function meta(string $source, float $startedAt): array
    {
        return [
            'source' => $source,
            'response_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ];
    }
}