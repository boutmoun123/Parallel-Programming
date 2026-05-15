<?php

namespace App\Modules\Orders\Controllers;

use App\Http\Controllers\Concerns\JsonApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Modules\Orders\Requests\StoreOrderRequest;
use App\Modules\Orders\Requests\UpdateOrderRequest;
use App\Modules\Orders\Resources\OrderResource;
use App\Modules\Orders\Services\OrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use JsonApiResponses;

    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $orders = $this->orderService->listForUser($request->user());

        return $this->success('Orders retrieved successfully', OrderResource::collection($orders)->resolve(), $startedAt);
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $order = $this->orderService->createForUser($request->validated(), $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Cart not found', ['cart_id' => ['The target cart does not exist for the current user.']], 404);
        }

        return $this->success('Order created successfully', (new OrderResource($order))->resolve(), $startedAt, 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $ownedOrder = $this->orderService->findForUser($order, $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Order not found', ['order' => ['The order does not exist for the current user.']], 404);
        }

        return $this->success('Order retrieved successfully', (new OrderResource($ownedOrder))->resolve(), $startedAt);
    }

    public function update(UpdateOrderRequest $request, Order $order): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $updatedOrder = $this->orderService->updateForUser($order, $request->validated(), $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Order not found', ['order' => ['The order or target cart does not exist for the current user.']], 404);
        }

        return $this->success('Order updated successfully', (new OrderResource($updatedOrder))->resolve(), $startedAt);
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $this->orderService->deleteForUser($order, $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Order not found', ['order' => ['The order does not exist for the current user.']], 404);
        }

        return $this->success('Order deleted successfully', null, $startedAt);
    }
}
