<?php

namespace App\Modules\Orders\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class OrderService
{
    public function listForUser(User $user): Collection
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForUser(array $data, User $user): Order
    {
        if (array_key_exists('cart_id', $data) && $data['cart_id'] !== null) {
            $this->assertOwnedCart((int) $data['cart_id'], $user);
        }

        return Order::create([
            'user_id' => $user->id,
            'cart_id' => $data['cart_id'] ?? null,
            'order_number' => $data['order_number'] ?? $this->generateOrderNumber(),
            'status' => $data['status'],
            'payment_status' => $data['payment_status'],
            'total_items' => (int) ($data['total_items'] ?? 0),
            'total_amount' => (float) ($data['total_amount'] ?? 0),
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function findForUser(Order $order, User $user): Order
    {
        if ($order->user_id !== $user->id) {
            throw (new ModelNotFoundException())->setModel(Order::class, [$order->id]);
        }

        return $order;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateForUser(Order $order, array $data, User $user): Order
    {
        $ownedOrder = $this->findForUser($order, $user);

        if (array_key_exists('cart_id', $data) && $data['cart_id'] !== null) {
            $this->assertOwnedCart((int) $data['cart_id'], $user);
        }

        $ownedOrder->fill($data);
        $ownedOrder->save();

        return $ownedOrder->fresh();
    }

    public function deleteForUser(Order $order, User $user): void
    {
        $ownedOrder = $this->findForUser($order, $user);

        OrderItem::query()
            ->where('order_id', $ownedOrder->id)
            ->delete();

        $ownedOrder->delete();
    }

    private function assertOwnedCart(int $cartId, User $user): void
    {
        $cartExists = Cart::query()
            ->whereKey($cartId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $cartExists) {
            throw (new ModelNotFoundException())->setModel(Cart::class, [$cartId]);
        }
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6));
    }
}
