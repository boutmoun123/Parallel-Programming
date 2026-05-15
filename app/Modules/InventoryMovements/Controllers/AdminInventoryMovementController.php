<?php

namespace App\Modules\InventoryMovements\Controllers;

use App\Http\Controllers\Concerns\JsonApiResponses;
use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Modules\InventoryMovements\Requests\StoreInventoryMovementRequest;
use App\Modules\InventoryMovements\Requests\UpdateInventoryMovementRequest;
use App\Modules\InventoryMovements\Resources\InventoryMovementResource;
use App\Modules\InventoryMovements\Services\InventoryMovementService;
use Illuminate\Http\JsonResponse;

class AdminInventoryMovementController extends Controller
{
    use JsonApiResponses;

    public function __construct(private readonly InventoryMovementService $inventoryMovementService)
    {
    }

    public function index(): JsonResponse
    {
        $startedAt = microtime(true);
        $movements = $this->inventoryMovementService->list();

        return $this->success('Inventory movements retrieved successfully', InventoryMovementResource::collection($movements)->resolve(), $startedAt);
    }

    public function store(StoreInventoryMovementRequest $request): JsonResponse
    {
        $startedAt = microtime(true);
        $movement = $this->inventoryMovementService->create($request->validated());

        return $this->success('Inventory movement created successfully', (new InventoryMovementResource($movement))->resolve(), $startedAt, 201);
    }

    public function show(InventoryMovement $inventoryMovement): JsonResponse
    {
        $startedAt = microtime(true);

        return $this->success('Inventory movement retrieved successfully', (new InventoryMovementResource($inventoryMovement))->resolve(), $startedAt);
    }

    public function update(UpdateInventoryMovementRequest $request, InventoryMovement $inventoryMovement): JsonResponse
    {
        $startedAt = microtime(true);
        $updatedMovement = $this->inventoryMovementService->update($inventoryMovement, $request->validated());

        return $this->success('Inventory movement updated successfully', (new InventoryMovementResource($updatedMovement))->resolve(), $startedAt);
    }

    public function destroy(InventoryMovement $inventoryMovement): JsonResponse
    {
        $startedAt = microtime(true);
        $this->inventoryMovementService->delete($inventoryMovement);

        return $this->success('Inventory movement deleted successfully', null, $startedAt);
    }
}
