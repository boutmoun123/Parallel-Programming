<?php

namespace App\Modules\Orders\Controllers;

use App\Http\Controllers\Concerns\JsonApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Modules\InventoryMovements\Resources\InventoryMovementResource;
use App\Modules\Orders\Exceptions\OrderCheckoutException;
use App\Modules\Orders\Requests\CheckoutOrderRequest;
use App\Modules\Orders\Requests\StoreOrderRequest;
use App\Modules\Orders\Requests\UpdateOrderRequest;
use App\Modules\Orders\Resources\OrderResource;
use App\Modules\Orders\Services\OrderCheckoutService;
use App\Modules\Orders\Services\OrderService;
use App\Modules\Payments\Resources\PaymentResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use JsonApiResponses;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderCheckoutService $orderCheckoutService,
    ) {
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

    public function checkout(CheckoutOrderRequest $request, Order $order): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $result = $this->orderCheckoutService->checkoutForUser($order, $request->validated(), $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Order not found', ['order' => ['The order does not exist for the current user.']], 404);
        } catch (OrderCheckoutException $exception) {
            return $this->error($exception->getMessage(), $exception->errors(), $exception->status());
        }

        return $this->success('Order checked out successfully', [
            'order' => (new OrderResource($result['order']))->resolve(),
            'payment' => (new PaymentResource($result['payment']))->resolve(),
            'inventory_movements' => InventoryMovementResource::collection($result['inventory_movements'])->resolve(),
        ], $startedAt);
    }
}
