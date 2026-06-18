<?php

namespace App\Modules\DailySalesReports\Controllers;

use App\Http\Controllers\Concerns\JsonApiResponses;
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
    use JsonApiResponses;

    public function __construct(
        private readonly DailySalesReportService $dailySalesReportService,
        private readonly DailySalesReportBatchService $dailySalesReportBatchService,
    ) {
    }

    public function index(): JsonResponse
    {
        $startedAt = microtime(true);
        $dailySalesReports = $this->dailySalesReportService->getLatestDailySalesReports();

        return $this->success('Daily sales reports retrieved successfully', DailySalesReportResource::collection($dailySalesReports)->resolve(), $startedAt);
    }

    public function store(StoreDailySalesReportRequest $request): JsonResponse
    {
        $startedAt = microtime(true);
        $dailySalesReport = $this->dailySalesReportService->createDailySalesReport($request->validated());

        return $this->success('Daily sales report created successfully', (new DailySalesReportResource($dailySalesReport))->resolve(), $startedAt, 201);
    }

    public function show(DailySalesReport $dailySalesReport): JsonResponse
    {
        $startedAt = microtime(true);

        return $this->success('Daily sales report retrieved successfully', (new DailySalesReportResource($dailySalesReport))->resolve(), $startedAt);
    }

    public function queueGenerate(QueueDailySalesReportRequest $request): JsonResponse
    {
        $startedAt = microtime(true);

        $reportDate = $request->validated('report_date');
        $this->dailySalesReportBatchService->queueForDate($reportDate);

        return $this->success('Daily sales report generation queued successfully', [
            'report_date' => $reportDate,
            'status' => 'queued',
        ], $startedAt, 202);
    }
}
