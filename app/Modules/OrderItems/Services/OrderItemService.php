<?php

namespace App\Modules\OrderItems\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OrderItemService
{
    public function listForUser(User $user): Collection
    {
        return OrderItem::query()
            ->whereIn('order_id', $this->ownedOrderIdsQuery($user))
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForUser(array $data, User $user): OrderItem
    {
        $this->assertOwnedOrder((int) $data['order_id'], $user);

        $orderItem = OrderItem::create($this->normalizeLineItemData($data));

        $this->syncOrderTotals((int) $orderItem->order_id);

        return $orderItem->fresh();
    }

    public function findForUser(OrderItem $orderItem, User $user): OrderItem
    {
        $ownedOrderItem = OrderItem::query()
            ->whereKey($orderItem->id)
            ->whereIn('order_id', $this->ownedOrderIdsQuery($user))
            ->first();

        if (! $ownedOrderItem) {
            throw (new ModelNotFoundException())->setModel(OrderItem::class, [$orderItem->id]);
        }

        return $ownedOrderItem;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateForUser(OrderItem $orderItem, array $data, User $user): OrderItem
    {
        $ownedOrderItem = $this->findForUser($orderItem, $user);
        $originalOrderId = (int) $ownedOrderItem->order_id;

        if (array_key_exists('order_id', $data)) {
            $this->assertOwnedOrder((int) $data['order_id'], $user);
        }

        $payload = array_merge(
            $ownedOrderItem->only(['order_id', 'product_id', 'product_name', 'quantity', 'unit_price']),
            $data,
        );

        $ownedOrderItem->fill($this->normalizeLineItemData($payload));
        $ownedOrderItem->save();

        $this->syncOrderTotals($originalOrderId);
        $this->syncOrderTotals((int) $ownedOrderItem->order_id);

        return $ownedOrderItem->fresh();
    }

    public function deleteForUser(OrderItem $orderItem, User $user): void
    {
        $ownedOrderItem = $this->findForUser($orderItem, $user);
        $orderId = (int) $ownedOrderItem->order_id;

        $ownedOrderItem->delete();

        $this->syncOrderTotals($orderId);
    }

    /**
     * @return Builder<Order>
     */
    private function ownedOrderIdsQuery(User $user): Builder
    {
        return Order::query()
            ->select('id')
            ->where('user_id', $user->id);
    }

    private function assertOwnedOrder(int $orderId, User $user): void
    {
        $orderExists = Order::query()
            ->whereKey($orderId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $orderExists) {
            throw (new ModelNotFoundException())->setModel(Order::class, [$orderId]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeLineItemData(array $data): array
    {
        $data['subtotal'] = round((float) $data['unit_price'] * (int) $data['quantity'], 2);

        return $data;
    }

    private function syncOrderTotals(int $orderId): void
    {
        $order = Order::query()->find($orderId);

        if (! $order) {
            return;
        }

        $totals = OrderItem::query()
            ->where('order_id', $orderId)
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_items')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as total_amount')
            ->first();

        $order->update([
            'total_items' => (int) ($totals?->total_items ?? 0),
            'total_amount' => (float) ($totals?->total_amount ?? 0),
        ]);
    }
}
