<?php

use App\Jobs\GenerateDailySalesReportJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reports:generate-daily-sales {date?}', function (?string $date = null) {
    $reportDate = $date ?? now()->toDateString();

    GenerateDailySalesReportJob::dispatch($reportDate);

    $this->info("Daily sales report job dispatched for {$reportDate}");
})->purpose('Dispatch a queued job that generates the daily sales report in chunks');

Schedule::call(function (): void {
    GenerateDailySalesReportJob::dispatch(now()->toDateString());
})->dailyAt('23:55');

Artisan::command('stress:validate-integrity', function () {
    $checks = [];

    $checks[] = [
        'name' => 'product stock_quantity is never negative',
        'passed' => \App\Models\Product::query()->where('stock_quantity', '<', 0)->doesntExist(),
    ];

    $checks[] = [
        'name' => 'product quantity_counter is never negative',
        'passed' => \App\Models\Product::query()->where('quantity_counter', '<', 0)->doesntExist(),
    ];

    $duplicateIdempotencyKeys = \App\Models\Payment::query()
        ->select('idempotency_key')
        ->groupBy('idempotency_key')
        ->havingRaw('COUNT(*) > 1')
        ->get()
        ->count();

    $checks[] = [
        'name' => 'no duplicate payments.idempotency_key',
        'passed' => $duplicateIdempotencyKeys === 0,
        'details' => "{$duplicateIdempotencyKeys} duplicate key groups",
    ];

    $completedOrdersWithoutPayment = \App\Models\Order::query()
        ->where(function ($query): void {
            $query->where('status', 'completed')
                ->orWhere('payment_status', 'paid');
        })
        ->whereNotExists(function ($query): void {
            $query->selectRaw('1')
                ->from('payments')
                ->whereColumn('payments.order_id', 'orders.id')
                ->where('payments.status', \App\Models\Payment::STATUS_COMPLETED);
        })
        ->count();

    $checks[] = [
        'name' => 'every completed order has a completed payment',
        'passed' => $completedOrdersWithoutPayment === 0,
        'details' => "{$completedOrdersWithoutPayment} completed orders without payment",
    ];

    $invalidSaleMovements = \App\Models\InventoryMovement::query()
        ->where('type', 'sale')
        ->where(function ($query): void {
            $query->whereRaw('stock_after != stock_before - quantity')
                ->orWhere('stock_after', '<', 0)
                ->orWhereColumn('quantity', '>', 'stock_before');
        })
        ->count();

    $checks[] = [
        'name' => 'inventory sale movements match stock decrements',
        'passed' => $invalidSaleMovements === 0,
        'details' => "{$invalidSaleMovements} invalid sale movements",
    ];

    $productsWithCounterAboveStock = \App\Models\Product::query()
        ->whereColumn('quantity_counter', '>', 'stock_quantity')
        ->count();

    $checks[] = [
        'name' => 'no overselling or impossible available counter',
        'passed' => $productsWithCounterAboveStock === 0,
        'details' => "{$productsWithCounterAboveStock} products have quantity_counter > stock_quantity",
    ];

    $this->line('Post-stress data integrity validation');
    $this->line('-------------------------------------');

    foreach ($checks as $check) {
        $status = $check['passed'] ? 'PASS' : 'FAIL';
        $details = isset($check['details']) ? " ({$check['details']})" : '';
        $this->line("[{$status}] {$check['name']}{$details}");
    }

    $failed = collect($checks)->contains(fn (array $check): bool => ! $check['passed']);

    if ($failed) {
        $this->error('Integrity validation failed.');

        return 1;
    }

    $this->info('Integrity validation passed.');

    return 0;
})->purpose('Validate stock, payment, idempotency, and inventory integrity after stress testing');

Artisan::command('benchmark:products-cache {--iterations=50}', function () {
    $iterations = max(1, (int) $this->option('iterations'));
    $service = app(\App\Modules\Products\Services\ProductService::class);
    $cache = \Illuminate\Support\Facades\Cache::store(config('cache.default'));

    $cache->forget('products:active:list');

    $coldStart = microtime(true);
    $service->activeProducts();
    $coldMs = (int) round((microtime(true) - $coldStart) * 1000);

    $warmStart = microtime(true);

    for ($i = 0; $i < $iterations; $i++) {
        $service->activeProducts();
    }

    $warmAverageMs = (int) round(((microtime(true) - $warmStart) * 1000) / $iterations);

    \App\Models\BenchmarkResult::query()->create([
        'operation_name' => 'products-cache',
        'scenario' => 'cold database read vs warm Redis cache read',
        'concurrent_users' => 1,
        'total_requests' => $iterations + 1,
        'successful_requests' => $iterations + 1,
        'failed_requests' => 0,
        'average_response_time_ms' => $warmAverageMs,
        'max_response_time_ms' => max($coldMs, $warmAverageMs),
        'throughput_per_second' => $warmAverageMs > 0 ? round(1000 / $warmAverageMs, 2) : null,
        'bottleneck_note' => "Cold read {$coldMs} ms; warm average {$warmAverageMs} ms over {$iterations} iterations.",
        'optimization_applied' => 'Redis product catalog cache',
        'tested_at' => now(),
    ]);

    $this->line('Products cache benchmark');
    $this->line("------------------------");
    $this->line("Cold database read: {$coldMs} ms");
    $this->line("Warm cache average: {$warmAverageMs} ms");
    $this->info('Benchmark result stored.');

    return 0;
})->purpose('Benchmark product listing before and after cache warmup');

Artisan::command('benchmark:checkout {--iterations=5}', function () {
    $iterations = max(1, (int) $this->option('iterations'));
    $service = app(\App\Modules\Orders\Services\OrderCheckoutService::class);
    $durations = [];

    for ($i = 0; $i < $iterations; $i++) {
        $user = \App\Models\User::query()->create([
            'name' => "Benchmark User {$i}",
            'phone' => '966599'.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'password' => 'Benchmark12345',
            'role' => 'user',
        ]);

        \App\Models\Wallet::query()->create([
            'user_id' => $user->id,
            'balance' => 1000,
        ]);

        $product = \App\Models\Product::query()->create([
            'name' => "Benchmark Product {$i}",
            'description' => 'Created by benchmark:checkout',
            'price' => 10,
            'stock_quantity' => 10,
            'quantity_counter' => 10,
            'status' => 'active',
            'photos' => [],
        ]);

        $order = \App\Models\Order::query()->create([
            'user_id' => $user->id,
            'order_number' => 'BENCH-'.now()->format('YmdHis').'-'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(6)),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_items' => 0,
            'total_amount' => 0,
        ]);

        \App\Models\OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => $product->price,
            'subtotal' => $product->price,
        ]);

        $startedAt = microtime(true);
        $service->checkoutForUser($order, [
            'payment_method' => 'wallet',
            'idempotency_key' => 'benchmark-checkout-'.$order->id.'-'.\Illuminate\Support\Str::uuid(),
        ], $user);
        $durations[] = (int) round((microtime(true) - $startedAt) * 1000);
    }

    $averageMs = (int) round(array_sum($durations) / count($durations));
    $maxMs = max($durations);

    \App\Models\BenchmarkResult::query()->create([
        'operation_name' => 'checkout',
        'scenario' => 'checkout with Redis locks, DB transactions, and queued post-payment work',
        'concurrent_users' => 1,
        'total_requests' => $iterations,
        'successful_requests' => $iterations,
        'failed_requests' => 0,
        'average_response_time_ms' => $averageMs,
        'max_response_time_ms' => $maxMs,
        'throughput_per_second' => $averageMs > 0 ? round(1000 / $averageMs, 2) : null,
        'bottleneck_note' => 'Checkout path includes wallet charge, row locks, stock update, order update, payment creation.',
        'optimization_applied' => 'Redis distributed locks + queued invoice/notification jobs',
        'tested_at' => now(),
    ]);

    $this->line('Checkout benchmark');
    $this->line('------------------');
    $this->line("Iterations: {$iterations}");
    $this->line("Average response time: {$averageMs} ms");
    $this->line("Max response time: {$maxMs} ms");
    $this->info('Benchmark result stored.');

    return 0;
})->purpose('Benchmark the optimized checkout path and store the result');
