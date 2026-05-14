<?php

use App\Modules\Products\Controllers\AdminProductController;
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

Route::prefix('admin/products')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function (): void {
        Route::post('/', [AdminProductController::class, 'store']);
        Route::put('/{product}', [AdminProductController::class, 'update']);
        Route::delete('/{product}', [AdminProductController::class, 'destroy']);
        Route::patch('/{product}/quantity', [AdminProductController::class, 'updateQuantity']);
    });
