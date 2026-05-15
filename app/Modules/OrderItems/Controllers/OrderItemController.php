<?php

namespace App\Modules\OrderItems\Controllers;

use App\Http\Controllers\Concerns\JsonApiResponses;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Modules\OrderItems\Requests\StoreOrderItemRequest;
use App\Modules\OrderItems\Requests\UpdateOrderItemRequest;
use App\Modules\OrderItems\Resources\OrderItemResource;
use App\Modules\OrderItems\Services\OrderItemService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    use JsonApiResponses;

    public function __construct(private readonly OrderItemService $orderItemService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $orderItems = $this->orderItemService->listForUser($request->user());

        return $this->success('Order items retrieved successfully', OrderItemResource::collection($orderItems)->resolve(), $startedAt);
    }

    public function store(StoreOrderItemRequest $request): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $orderItem = $this->orderItemService->createForUser($request->validated(), $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Order not found', ['order_id' => ['The target order does not exist for the current user.']], 404);
        }

        return $this->success('Order item created successfully', (new OrderItemResource($orderItem))->resolve(), $startedAt, 201);
    }

    public function show(Request $request, OrderItem $orderItem): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $ownedOrderItem = $this->orderItemService->findForUser($orderItem, $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Order item not found', ['order_item' => ['The order item does not exist for the current user.']], 404);
        }

        return $this->success('Order item retrieved successfully', (new OrderItemResource($ownedOrderItem))->resolve(), $startedAt);
    }

    public function update(UpdateOrderItemRequest $request, OrderItem $orderItem): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $updatedOrderItem = $this->orderItemService->updateForUser($orderItem, $request->validated(), $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Order item not found', ['order_item' => ['The order item or target order does not exist for the current user.']], 404);
        }

        return $this->success('Order item updated successfully', (new OrderItemResource($updatedOrderItem))->resolve(), $startedAt);
    }

    public function destroy(Request $request, OrderItem $orderItem): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $this->orderItemService->deleteForUser($orderItem, $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Order item not found', ['order_item' => ['The order item does not exist for the current user.']], 404);
        }

        return $this->success('Order item deleted successfully', null, $startedAt);
    }
}
