<?php

namespace App\Modules\DailySalesReportItems\Controllers;

use App\Http\Controllers\Controller;
use App\Models\DailySalesReportItem;
use App\Modules\DailySalesReportItems\Requests\StoreDailySalesReportItemRequest;
use App\Modules\DailySalesReportItems\Resources\DailySalesReportItemResource;
use App\Modules\DailySalesReportItems\Services\DailySalesReportItemService;
use Illuminate\Http\JsonResponse;

class DailySalesReportItemController extends Controller
{
    public function __construct(private readonly DailySalesReportItemService $dailySalesReportItemService)
    {
    }

    public function index(): JsonResponse
    {
        $dailySalesReportItems = $this->dailySalesReportItemService->getLatestDailySalesReportItems();

        return $this->success('Daily sales report items retrieved successfully', DailySalesReportItemResource::collection($dailySalesReportItems));
    }

    public function store(StoreDailySalesReportItemRequest $request): JsonResponse
    {
        $dailySalesReportItem = $this->dailySalesReportItemService->createDailySalesReportItem($request->validated());

        return $this->success('Daily sales report item created successfully', new DailySalesReportItemResource($dailySalesReportItem), 201);
    }

    public function show(DailySalesReportItem $dailySalesReportItem): JsonResponse
    {
        return $this->success('Daily sales report item retrieved successfully', new DailySalesReportItemResource($dailySalesReportItem));
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
