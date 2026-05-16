<?php

namespace App\Modules\Orders\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Modules\InventoryMovements\Services\InventoryMovementService;
use App\Modules\Orders\Exceptions\OrderCheckoutException;
use App\Modules\Payments\Services\PaymentService;
use App\Modules\Products\Services\ProductService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class OrderCheckoutService
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly InventoryMovementService $inventoryMovementService,
        private readonly ProductService $productService,
    ) {
    }

    /**
     * @param  array{payment_method: string, idempotency_key: string}  $data
     * @return array{
     *     order: Order,
     *     payment: Payment,
     *     inventory_movements: Collection<int, \App\Models\InventoryMovement>
     * }
     */
    public function checkoutForUser(Order $order, array $data, User $user): array
    {
        return DB::transaction(function () use ($order, $data, $user): array {
            // Lock the order row first so duplicate checkout attempts are serialized.
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedOrder || $lockedOrder->user_id !== $user->id) {
                throw (new ModelNotFoundException())->setModel(Order::class, [$order->id]);
            }

            if ($lockedOrder->payment_status === 'paid') {
                throw new OrderCheckoutException(
                    'Order has already been paid.',
                    ['order' => ['This order has already been checked out.']],
                    409,
                );
            }

            $existingPayment = Payment::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existingPayment !== null) {
                throw new OrderCheckoutException(
                    'Idempotency key has already been used.',
                    ['idempotency_key' => ['Use a new idempotency key for a new checkout attempt.']],
                    409,
                );
            }

            $orderItems = OrderItem::query()
                ->where('order_id', $lockedOrder->id)
                ->orderBy('product_id')
                ->get();

            if ($orderItems->isEmpty()) {
                throw new OrderCheckoutException(
                    'Order cannot be checked out without items.',
                    ['order' => ['Add at least one order item before checkout.']],
                    422,
                );
            }

            // Lock products in a stable order to protect shared stock and reduce deadlock risk.
            $lockedProducts = Product::query()
                ->whereIn('id', $orderItems->pluck('product_id')->unique()->sort()->values())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $totalItems = 0;
            $totalAmount = 0.0;
            $movements = collect();

            foreach ($orderItems as $orderItem) {
                $product = $lockedProducts->get($orderItem->product_id);

                if (! $product || $product->status !== 'active') {
                    throw new OrderCheckoutException(
                        'Product is not available for checkout.',
                        ['product' => ["Product {$orderItem->product_id} is inactive or missing."]],
                        409,
                    );
                }

                if ($product->quantity_counter < $orderItem->quantity || $product->stock_quantity < $orderItem->quantity) {
                    throw new OrderCheckoutException(
                        'Requested quantity is no longer available.',
                        [
                            'product' => [
                                "Product {$product->id} only has {$product->quantity_counter} units available for immediate checkout.",
                            ],
                        ],
                        409,
                    );
                }

                $stockBefore = $product->stock_quantity;

                $product->quantity_counter -= $orderItem->quantity;
                $product->stock_quantity -= $orderItem->quantity;
                $product->save();

                $this->productService->forgetProductCaches($product->id);

                $movements->push($this->inventoryMovementService->create([
                    'product_id' => $product->id,
                    'order_id' => $lockedOrder->id,
                    'order_item_id' => $orderItem->id,
                    'type' => 'sale',
                    'quantity' => $orderItem->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $product->stock_quantity,
                    'unit_price' => $orderItem->unit_price,
                    'reason' => 'Atomic checkout completed',
                ]));

                $totalItems += (int) $orderItem->quantity;
                $totalAmount += (float) $orderItem->subtotal;
            }

            $lockedOrder->fill([
                'status' => 'completed',
                'payment_status' => 'paid',
                'total_items' => $totalItems,
                'total_amount' => round($totalAmount, 2),
            ]);
            $lockedOrder->save();

            $payment = $this->paymentService->createPayment([
                'order_id' => $lockedOrder->id,
                'payment_method' => $data['payment_method'],
                'idempotency_key' => $data['idempotency_key'],
                'amount' => round($totalAmount, 2),
                'status' => Payment::STATUS_COMPLETED,
            ]);

            return [
                'order' => $lockedOrder->fresh(),
                'payment' => $payment,
                'inventory_movements' => $movements,
            ];
        });
    }
}
