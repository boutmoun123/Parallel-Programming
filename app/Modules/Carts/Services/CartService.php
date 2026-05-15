<?php

namespace App\Modules\Carts\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CartService
{
    public function listForUser(User $user): Collection
    {
        return Cart::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createForUser(array $data, User $user): Cart
    {
        return Cart::create([
            'user_id' => $user->id,
            'status' => $data['status'],
            'total_items' => (int) ($data['total_items'] ?? 0),
            'total_amount' => (float) ($data['total_amount'] ?? 0),
        ]);
    }

    public function findForUser(Cart $cart, User $user): Cart
    {
        if ($cart->user_id !== $user->id) {
            throw (new ModelNotFoundException())->setModel(Cart::class, [$cart->id]);
        }

        return $cart;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateForUser(Cart $cart, array $data, User $user): Cart
    {
        $ownedCart = $this->findForUser($cart, $user);

        $ownedCart->fill($data);
        $ownedCart->save();

        return $ownedCart->fresh();
    }

    public function deleteForUser(Cart $cart, User $user): void
    {
        $ownedCart = $this->findForUser($cart, $user);

        CartItem::query()
            ->where('cart_id', $ownedCart->id)
            ->delete();

        $ownedCart->delete();
    }
}
