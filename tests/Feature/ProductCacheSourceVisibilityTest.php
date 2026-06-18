<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCacheSourceVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_cache_source_is_visible_only_when_app_debug_is_enabled(): void
    {
        Product::create([
            'name' => 'Debug Cache Product',
            'description' => 'Used for cache source visibility tests',
            'price' => 15,
            'stock_quantity' => 5,
            'quantity_counter' => 5,
            'status' => 'active',
            'photos' => [],
        ]);

        config(['app.debug' => false]);

        $productionResponse = $this->getJson('/api/products')
            ->assertOk()
            ->json('meta');

        $this->assertIsArray($productionResponse);
        $this->assertArrayNotHasKey('cache_source', $productionResponse);

        config(['app.debug' => true]);

        $debugResponse = $this->getJson('/api/products')
            ->assertOk()
            ->json('meta');

        $this->assertIsArray($debugResponse);
        $this->assertArrayHasKey('cache_source', $debugResponse);
    }
}
