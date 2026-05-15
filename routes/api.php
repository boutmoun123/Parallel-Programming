<?php

use App\Modules\BenchmarkResults\Controllers\BenchmarkResultController;
use App\Modules\FailedJobs\Controllers\FailedJobController;
use App\Modules\Invoices\Controllers\InvoiceController;
use App\Modules\Notifications\Controllers\NotificationController;
use App\Modules\Payments\Controllers\PaymentController;
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

Route::prefix('payments')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/', [PaymentController::class, 'index']);
        Route::post('/', [PaymentController::class, 'store']);
        Route::get('/{payment}', [PaymentController::class, 'show']);
    });

Route::prefix('invoices')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::post('/', [InvoiceController::class, 'store']);
        Route::get('/{invoice}', [InvoiceController::class, 'show']);
    });

Route::prefix('notifications')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/', [NotificationController::class, 'store']);
        Route::get('/{notification}', [NotificationController::class, 'show']);
    });

Route::prefix('benchmark-results')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/', [BenchmarkResultController::class, 'index']);
        Route::post('/', [BenchmarkResultController::class, 'store']);
        Route::get('/{benchmarkResult}', [BenchmarkResultController::class, 'show']);
    });

Route::prefix('failed-jobs')
    ->middleware('auth:sanctum')
    ->group(function (): void {
        Route::get('/', [FailedJobController::class, 'index']);
        Route::get('/{failedJob}', [FailedJobController::class, 'show']);
    });

Route::prefix('admin/products')
    ->middleware(['auth:sanctum', 'admin'])
    ->group(function (): void {
        Route::post('/', [AdminProductController::class, 'store']);
        Route::put('/{product}', [AdminProductController::class, 'update']);
        Route::delete('/{product}', [AdminProductController::class, 'destroy']);
        Route::patch('/{product}/quantity', [AdminProductController::class, 'updateQuantity']);
    });
