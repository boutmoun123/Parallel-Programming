<?php

namespace App\Modules\CartItems\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CartItemService
{
    public function listForUser(User $user): Collection
    {
        return CartItem::query()
            ->whereIn('cart_id', $this->ownedCartIdsQuery($user))
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForUser(array $data, User $user): CartItem
    {
        $this->assertOwnedCart((int) $data['cart_id'], $user);

        $cartItem = CartItem::create($this->normalizeLineItemData($data));

        $this->syncCartTotals((int) $cartItem->cart_id);

        return $cartItem->fresh();
    }

    public function findForUser(CartItem $cartItem, User $user): CartItem
    {
        $ownedCartItem = CartItem::query()
            ->whereKey($cartItem->id)
            ->whereIn('cart_id', $this->ownedCartIdsQuery($user))
            ->first();

        if (! $ownedCartItem) {
            throw (new ModelNotFoundException())->setModel(CartItem::class, [$cartItem->id]);
        }

        return $ownedCartItem;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateForUser(CartItem $cartItem, array $data, User $user): CartItem
    {
        $ownedCartItem = $this->findForUser($cartItem, $user);
        $originalCartId = (int) $ownedCartItem->cart_id;

        if (array_key_exists('cart_id', $data)) {
            $this->assertOwnedCart((int) $data['cart_id'], $user);
        }

        $payload = array_merge(
            $ownedCartItem->only(['cart_id', 'product_id', 'product_name', 'quantity', 'unit_price']),
            $data,
        );

        $ownedCartItem->fill($this->normalizeLineItemData($payload));
        $ownedCartItem->save();

        $this->syncCartTotals($originalCartId);
        $this->syncCartTotals((int) $ownedCartItem->cart_id);

        return $ownedCartItem->fresh();
    }

    public function deleteForUser(CartItem $cartItem, User $user): void
    {
        $ownedCartItem = $this->findForUser($cartItem, $user);
        $cartId = (int) $ownedCartItem->cart_id;

        $ownedCartItem->delete();

        $this->syncCartTotals($cartId);
    }

    /**
     * @return Builder<Cart>
     */
    private function ownedCartIdsQuery(User $user): Builder
    {
        return Cart::query()
            ->select('id')
            ->where('user_id', $user->id);
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeLineItemData(array $data): array
    {
        $data['subtotal'] = round((float) $data['unit_price'] * (int) $data['quantity'], 2);

        return $data;
    }

    private function syncCartTotals(int $cartId): void
    {
        $cart = Cart::query()->find($cartId);

        if (! $cart) {
            return;
        }

        $totals = CartItem::query()
            ->where('cart_id', $cartId)
            ->selectRaw('COALESCE(SUM(quantity), 0) as total_items')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as total_amount')
            ->first();

        $cart->update([
            'total_items' => (int) ($totals?->total_items ?? 0),
            'total_amount' => (float) ($totals?->total_amount ?? 0),
        ]);
    }
}
