<?php

use App\Modules\Products\Controllers\AdminProductController;
use App\Modules\CartItems\Controllers\CartItemController;
use App\Modules\Carts\Controllers\CartController;
use App\Modules\InventoryMovements\Controllers\AdminInventoryMovementController;
use App\Modules\OrderItems\Controllers\OrderItemController;
use App\Modules\Orders\Controllers\OrderController;
use App\Modules\Products\Controllers\ProductController;
use App\Modules\Users\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'server']);
});

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::prefix('products')->group(function (): void {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/most-ordered', [ProductController::class, 'mostOrdered']);
    Route::get('/{product}', [ProductController::class, 'show']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('carts')->group(function (): void {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::get('/{cart}', [CartController::class, 'show']);
        Route::put('/{cart}', [CartController::class, 'update']);
        Route::delete('/{cart}', [CartController::class, 'destroy']);
    });

    Route::prefix('cart-items')->group(function (): void {
        Route::get('/', [CartItemController::class, 'index']);
        Route::post('/', [CartItemController::class, 'store']);
        Route::get('/{cartItem}', [CartItemController::class, 'show']);
        Route::put('/{cartItem}', [CartItemController::class, 'update']);
        Route::delete('/{cartItem}', [CartItemController::class, 'destroy']);
    });

    Route::prefix('orders')->group(function (): void {
        Route::get('/', [OrderController::class, 'index']);
        Route::post('/', [OrderController::class, 'store']);
        Route::get('/{order}', [OrderController::class, 'show']);
        Route::put('/{order}', [OrderController::class, 'update']);
        Route::delete('/{order}', [OrderController::class, 'destroy']);
    });

    Route::prefix('order-items')->group(function (): void {
        Route::get('/', [OrderItemController::class, 'index']);
        Route::post('/', [OrderItemController::class, 'store']);
        Route::get('/{orderItem}', [OrderItemController::class, 'show']);
        Route::put('/{orderItem}', [OrderItemController::class, 'update']);
        Route::delete('/{orderItem}', [OrderItemController::class, 'destroy']);
    });
});

Route::prefix('admin/products')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function (): void {
        Route::post('/', [AdminProductController::class, 'store']);
        Route::put('/{product}', [AdminProductController::class, 'update']);
        Route::delete('/{product}', [AdminProductController::class, 'destroy']);
        Route::patch('/{product}/quantity', [AdminProductController::class, 'updateQuantity']);
    });

Route::prefix('admin/inventory-movements')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function (): void {
        Route::get('/', [AdminInventoryMovementController::class, 'index']);
        Route::post('/', [AdminInventoryMovementController::class, 'store']);
        Route::get('/{inventoryMovement}', [AdminInventoryMovementController::class, 'show']);
        Route::put('/{inventoryMovement}', [AdminInventoryMovementController::class, 'update']);
        Route::delete('/{inventoryMovement}', [AdminInventoryMovementController::class, 'destroy']);
    });
