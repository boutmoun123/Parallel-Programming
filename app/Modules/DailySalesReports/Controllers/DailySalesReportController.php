<?php

namespace App\Modules\DailySalesReports\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DailySalesReport;
use App\Modules\DailySalesReports\Requests\StoreDailySalesReportRequest;
use App\Modules\DailySalesReports\Resources\DailySalesReportResource;
use App\Modules\DailySalesReports\Services\DailySalesReportService;
use Illuminate\Http\JsonResponse;

class DailySalesReportController extends Controller
{
    public function __construct(private readonly DailySalesReportService $dailySalesReportService)
    {
    }

    public function index(): JsonResponse
    {
        $dailySalesReports = $this->dailySalesReportService->getLatestDailySalesReports();

        return $this->success('Daily sales reports retrieved successfully', DailySalesReportResource::collection($dailySalesReports));
    }

    public function store(StoreDailySalesReportRequest $request): JsonResponse
    {
        $dailySalesReport = $this->dailySalesReportService->createDailySalesReport($request->validated());

        return $this->success('Daily sales report created successfully', new DailySalesReportResource($dailySalesReport), 201);
    }

    public function show(DailySalesReport $dailySalesReport): JsonResponse
    {
        return $this->success('Daily sales report retrieved successfully', new DailySalesReportResource($dailySalesReport));
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
