<?php

use App\Modules\BenchmarkResults\Controllers\BenchmarkResultController;
use App\Modules\DailySalesReportItems\Controllers\DailySalesReportItemController;
use App\Modules\DailySalesReports\Controllers\DailySalesReportController;
use App\Modules\FailedJobs\Controllers\FailedJobController;
use App\Modules\Invoices\Controllers\InvoiceController;
use App\Modules\Notifications\Controllers\NotificationController;
use App\Modules\Payments\Controllers\PaymentController;
use App\Modules\Products\Controllers\AdminProductController;
use App\Modules\CartItems\Controllers\CartItemController;
use App\Modules\Carts\Controllers\CartController;
use App\Modules\InventoryMovements\Controllers\AdminInventoryMovementController;
use App\Modules\OrderItems\Controllers\OrderItemController;
use App\Modules\Orders\Controllers\OrderController;
use App\Modules\Products\Controllers\ProductController;
use App\Modules\RequestLogs\Controllers\RequestLogController;
use App\Modules\ServerNodes\Controllers\ServerNodeController;
use App\Modules\Users\Controllers\AuthController;
use App\Modules\Wallets\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'server']);
});

Route::middleware('load-balance')->group(function (): void {
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
            Route::post('/', [PaymentController::class, 'store'])->middleware('capacity:critical-operations');
            Route::get('/{payment}', [PaymentController::class, 'show']);
        });

    Route::prefix('wallet')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::get('/', [WalletController::class, 'show']);
            Route::post('/deposit', [WalletController::class, 'deposit'])->middleware('capacity:critical-operations');
        });

    Route::prefix('invoices')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::get('/', [InvoiceController::class, 'index']);
            Route::post('/', [InvoiceController::class, 'store'])->middleware('capacity:critical-operations');
            Route::get('/{invoice}', [InvoiceController::class, 'show']);
        });

    Route::prefix('notifications')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::get('/', [NotificationController::class, 'index']);
            Route::post('/', [NotificationController::class, 'store'])->middleware('capacity:critical-operations');
            Route::get('/{notification}', [NotificationController::class, 'show']);
        });

    Route::prefix('benchmark-results')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::get('/', [BenchmarkResultController::class, 'index']);
            Route::post('/', [BenchmarkResultController::class, 'store'])->middleware('capacity:critical-operations');
            Route::get('/{benchmarkResult}', [BenchmarkResultController::class, 'show']);
        });

    Route::prefix('failed-jobs')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::get('/', [FailedJobController::class, 'index']);
            Route::get('/{failedJob}', [FailedJobController::class, 'show']);
        });

    Route::prefix('server-nodes')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::get('/', [ServerNodeController::class, 'index']);
            Route::post('/', [ServerNodeController::class, 'store'])->middleware('capacity:critical-operations');
            Route::get('/{serverNode}', [ServerNodeController::class, 'show']);
            Route::put('/{serverNode}', [ServerNodeController::class, 'update'])->middleware('capacity:critical-operations');
        });

    Route::prefix('request-logs')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::get('/', [RequestLogController::class, 'index']);
            Route::post('/', [RequestLogController::class, 'store'])->middleware('capacity:critical-operations');
            Route::get('/{requestLog}', [RequestLogController::class, 'show']);
        });

    Route::prefix('daily-sales-reports')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::get('/', [DailySalesReportController::class, 'index']);
            Route::post('/generate', [DailySalesReportController::class, 'queueGenerate'])->middleware('capacity:critical-operations');
            Route::post('/', [DailySalesReportController::class, 'store'])->middleware('capacity:critical-operations');
            Route::get('/{dailySalesReport}', [DailySalesReportController::class, 'show']);
        });

    Route::prefix('daily-sales-report-items')
        ->middleware('auth:sanctum')
        ->group(function (): void {
            Route::get('/', [DailySalesReportItemController::class, 'index']);
            Route::post('/', [DailySalesReportItemController::class, 'store'])->middleware('capacity:critical-operations');
            Route::get('/{dailySalesReportItem}', [DailySalesReportItemController::class, 'show']);
        });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::prefix('carts')->group(function (): void {
            Route::get('/', [CartController::class, 'index']);
            Route::post('/', [CartController::class, 'store'])->middleware('capacity:critical-operations');
            Route::get('/{cart}', [CartController::class, 'show']);
            Route::put('/{cart}', [CartController::class, 'update'])->middleware('capacity:critical-operations');
            Route::delete('/{cart}', [CartController::class, 'destroy'])->middleware('capacity:critical-operations');
        });

        Route::prefix('cart-items')->group(function (): void {
            Route::get('/', [CartItemController::class, 'index']);
            Route::post('/', [CartItemController::class, 'store'])->middleware('capacity:critical-operations');
            Route::get('/{cartItem}', [CartItemController::class, 'show']);
            Route::put('/{cartItem}', [CartItemController::class, 'update'])->middleware('capacity:critical-operations');
            Route::delete('/{cartItem}', [CartItemController::class, 'destroy'])->middleware('capacity:critical-operations');
        });

        Route::prefix('orders')->group(function (): void {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store'])->middleware('capacity:critical-operations');
            Route::get('/{order}', [OrderController::class, 'show']);
            Route::post('/{order}/checkout', [OrderController::class, 'checkout'])->middleware('capacity:checkout');
            Route::put('/{order}', [OrderController::class, 'update'])->middleware('capacity:critical-operations');
            Route::delete('/{order}', [OrderController::class, 'destroy'])->middleware('capacity:critical-operations');
        });

        Route::prefix('order-items')->group(function (): void {
            Route::get('/', [OrderItemController::class, 'index']);
            Route::post('/', [OrderItemController::class, 'store'])->middleware('capacity:critical-operations');
            Route::get('/{orderItem}', [OrderItemController::class, 'show']);
            Route::put('/{orderItem}', [OrderItemController::class, 'update'])->middleware('capacity:critical-operations');
            Route::delete('/{orderItem}', [OrderItemController::class, 'destroy'])->middleware('capacity:critical-operations');
        });
    });

    Route::prefix('admin/products')
        ->middleware(['auth:sanctum', 'admin'])
        ->group(function (): void {
            Route::post('/', [AdminProductController::class, 'store'])->middleware('capacity:critical-operations');
            Route::put('/{product}', [AdminProductController::class, 'update'])->middleware('capacity:critical-operations');
            Route::delete('/{product}', [AdminProductController::class, 'destroy'])->middleware('capacity:critical-operations');
            Route::patch('/{product}/quantity', [AdminProductController::class, 'updateQuantity'])->middleware('capacity:critical-operations');
        });

    Route::prefix('admin/inventory-movements')
        ->middleware(['auth:sanctum', 'admin'])
        ->group(function (): void {
            Route::get('/', [AdminInventoryMovementController::class, 'index']);
            Route::post('/', [AdminInventoryMovementController::class, 'store'])->middleware('capacity:critical-operations');
            Route::get('/{inventoryMovement}', [AdminInventoryMovementController::class, 'show']);
            Route::put('/{inventoryMovement}', [AdminInventoryMovementController::class, 'update'])->middleware('capacity:critical-operations');
            Route::delete('/{inventoryMovement}', [AdminInventoryMovementController::class, 'destroy'])->middleware('capacity:critical-operations');
        });
});
