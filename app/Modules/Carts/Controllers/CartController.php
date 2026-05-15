<?php

namespace App\Modules\Carts\Controllers;

use App\Http\Controllers\Concerns\JsonApiResponses;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Modules\Carts\Requests\StoreCartRequest;
use App\Modules\Carts\Requests\UpdateCartRequest;
use App\Modules\Carts\Resources\CartResource;
use App\Modules\Carts\Services\CartService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use JsonApiResponses;

    public function __construct(private readonly CartService $cartService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $startedAt = microtime(true);
        $carts = $this->cartService->listForUser($request->user());

        return $this->success('Carts retrieved successfully', CartResource::collection($carts)->resolve(), $startedAt);
    }

    public function store(StoreCartRequest $request): JsonResponse
    {
        $startedAt = microtime(true);
        $cart = $this->cartService->createForUser($request->validated(), $request->user());

        return $this->success('Cart created successfully', (new CartResource($cart))->resolve(), $startedAt, 201);
    }

    public function show(Request $request, Cart $cart): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $ownedCart = $this->cartService->findForUser($cart, $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Cart not found', ['cart' => ['The cart does not exist for the current user.']], 404);
        }

        return $this->success('Cart retrieved successfully', (new CartResource($ownedCart))->resolve(), $startedAt);
    }

    public function update(UpdateCartRequest $request, Cart $cart): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $updatedCart = $this->cartService->updateForUser($cart, $request->validated(), $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Cart not found', ['cart' => ['The cart does not exist for the current user.']], 404);
        }

        return $this->success('Cart updated successfully', (new CartResource($updatedCart))->resolve(), $startedAt);
    }

    public function destroy(Request $request, Cart $cart): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $this->cartService->deleteForUser($cart, $request->user());
        } catch (ModelNotFoundException) {
            return $this->error('Cart not found', ['cart' => ['The cart does not exist for the current user.']], 404);
        }

        return $this->success('Cart deleted successfully', null, $startedAt);
    }
}
