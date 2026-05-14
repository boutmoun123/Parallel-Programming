<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Modules\Products\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductQuantityCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_browse_and_details_expose_quantity_counter(): void
    {
        $product = Product::create([
            'name' => 'Gaming Laptop',
            'description' => 'High performance gaming laptop',
            'price' => 1500,
            'stock_quantity' => 2,
            'quantity_counter' => 2,
            'status' => 'active',
            'photos' => [],
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('data.0.quantity_counter', 2);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.quantity_counter', 2);
    }

    public function test_product_creation_initializes_quantity_counter_from_stock_quantity(): void
    {
        $product = app(ProductService::class)->createProduct([
            'name' => 'Wireless Mouse',
            'description' => 'Ergonomic wireless mouse',
            'price' => 45.99,
            'stock_quantity' => 100,
            'status' => 'active',
            'photos' => [],
        ]);

        $this->assertSame(100, $product->quantity_counter);
        $this->assertSame(100, $product->stock_quantity);
    }

    public function test_admin_can_create_product_with_uploaded_photos(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9WlH0yQAAAAASUVORK5CYII=');

        Sanctum::actingAs(User::create([
            'name' => 'Admin User',
            'phone' => '966500000001',
            'password' => 'Admin12345',
            'role' => 'admin',
        ]));

        $response = $this->post('/api/admin/products', [
            'name' => 'Uploaded Product',
            'description' => 'Created with uploaded files',
            'price' => '149.99',
            'stock_quantity' => '25',
            'quantity_counter' => '25',
            'status' => 'active',
            'photos' => [
                UploadedFile::fake()->createWithContent('first.png', $png),
                UploadedFile::fake()->createWithContent('second.png', $png),
            ],
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.photos');

        $product = Product::query()->firstOrFail();

        $this->assertCount(2, $product->photos);
        Storage::disk('public')->assertExists($product->photos[0]);
        Storage::disk('public')->assertExists($product->photos[1]);
        $this->assertStringContainsString('/storage/products/', $response->json('data.photos.0'));
    }

    public function test_reservation_decreases_quantity_counter_without_changing_stock_quantity(): void
    {
        $product = $this->product(stockQuantity: 2, quantityCounter: 2);

        $reservedProduct = app(ProductService::class)->reserveQuantityWithLock($product, 2);

        $this->assertSame(0, $reservedProduct->quantity_counter);
        $this->assertSame(2, $reservedProduct->stock_quantity);
    }

    public function test_unavailable_quantity_cannot_be_reserved(): void
    {
        $product = $this->product(stockQuantity: 2, quantityCounter: 0);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Requested quantity is not available for reservation.');

        app(ProductService::class)->reserveQuantityWithLock($product, 1);
    }

    public function test_successful_payment_decreases_stock_quantity_and_keeps_counter_reserved(): void
    {
        $product = $this->product(stockQuantity: 2, quantityCounter: 2);
        $service = app(ProductService::class);

        $service->reserveQuantityWithLock($product, 2);
        $paidProduct = $service->decrementStockAfterPaymentWithLock($product, 2);

        $this->assertSame(0, $paidProduct->stock_quantity);
        $this->assertSame(0, $paidProduct->quantity_counter);
    }

    public function test_cancelled_or_expired_payment_restores_quantity_counter_without_changing_stock(): void
    {
        $product = $this->product(stockQuantity: 2, quantityCounter: 2);
        $service = app(ProductService::class);

        $service->reserveQuantityWithLock($product, 2);
        $restoredProduct = $service->restoreReservedQuantityWithLock($product, 2);

        $this->assertSame(2, $restoredProduct->quantity_counter);
        $this->assertSame(2, $restoredProduct->stock_quantity);
    }

    private function product(int $stockQuantity, int $quantityCounter): Product
    {
        return Product::create([
            'name' => 'Mechanical Keyboard',
            'description' => 'RGB mechanical keyboard',
            'price' => 120.50,
            'stock_quantity' => $stockQuantity,
            'quantity_counter' => $quantityCounter,
            'status' => 'active',
            'photos' => [],
        ]);
    }
}
