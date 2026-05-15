<?php

namespace App\Modules\CartItems\Controllers;

use App\Http\Controllers\Concerns\JsonApiResponses;
use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Modules\CartItems\Requests\StoreCartItemRequest;
use App\Modules\CartItems\Requests\UpdateCartItemRequest;
use App\Modules\CartItems\Resources\CartItemResource;
use App\Modules\CartItems\Services\CartItemService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartItemController extends Controller
{
    use JsonApiResponses;

    public function __construct(private readonly CartItemService $cartItemService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $cartItems = $this->cartItemService->listForUser($request->user());

        return $this->success('Cart items retrieved successfully', CartItemResource::collection($cartItems)->resolve(), $startedAt);
    }

    public function store(StoreCartItemRequest $request): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $cartItem = $this->cartItemService->createForUser($request->validated(), $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Cart not found', ['cart_id' => ['The target cart does not exist for the current user.']], 404);
        }

        return $this->success('Cart item created successfully', (new CartItemResource($cartItem))->resolve(), $startedAt, 201);
    }

    public function show(Request $request, CartItem $cartItem): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $ownedCartItem = $this->cartItemService->findForUser($cartItem, $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Cart item not found', ['cart_item' => ['The cart item does not exist for the current user.']], 404);
        }

        return $this->success('Cart item retrieved successfully', (new CartItemResource($ownedCartItem))->resolve(), $startedAt);
    }

    public function update(UpdateCartItemRequest $request, CartItem $cartItem): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $updatedCartItem = $this->cartItemService->updateForUser($cartItem, $request->validated(), $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Cart item not found', ['cart_item' => ['The cart item does not exist for the current user.']], 404);
        }

        return $this->success('Cart item updated successfully', (new CartItemResource($updatedCartItem))->resolve(), $startedAt);
    }

    public function destroy(Request $request, CartItem $cartItem): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $this->cartItemService->deleteForUser($cartItem, $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Cart item not found', ['cart_item' => ['The cart item does not exist for the current user.']], 404);
        }

        return $this->success('Cart item deleted successfully', null, $startedAt);
    }
}
