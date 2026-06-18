<?php

namespace Tests\Feature;

use App\Models\BenchmarkResult;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BenchmarkCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_cache_benchmark_stores_before_and_after_results(): void
    {
        Product::create([
            'name' => 'Benchmark Cache Product',
            'description' => 'Used for benchmark command tests',
            'price' => 25,
            'stock_quantity' => 10,
            'quantity_counter' => 10,
            'status' => 'active',
            'photos' => [],
        ]);

        Artisan::call('benchmark:products-cache', [
            '--iterations' => 2,
        ]);

        $this->assertSame(2, BenchmarkResult::query()
            ->where('operation_name', 'products-cache')
            ->count());

        $this->assertDatabaseHas('benchmark_results', [
            'operation_name' => 'products-cache',
            'scenario' => 'before Redis warmup: cold database read',
        ]);

        $this->assertDatabaseHas('benchmark_results', [
            'operation_name' => 'products-cache',
            'scenario' => 'after Redis warmup: cached product read',
        ]);
    }
}
