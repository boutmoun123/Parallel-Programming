<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_manage_carts_and_cart_items(): void
    {
        $user = $this->actingUser();
        $product = $this->createProduct(49.99);

        $cartResponse = $this->postJson('/api/carts', [
            'status' => 'active',
        ]);

        $cartResponse->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.total_items', 0)
            ->assertJsonPath('data.total_amount', '0.00');

        $cartId = $cartResponse->json('data.id');

        $this->getJson('/api/carts')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/carts/{$cartId}")
            ->assertOk()
            ->assertJsonPath('data.id', $cartId);

        $cartItemResponse = $this->postJson('/api/cart-items', [
            'cart_id' => $cartId,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $cartItemResponse->assertCreated()
            ->assertJsonPath('data.cart_id', $cartId)
            ->assertJsonPath('data.product_name', $product->name)
            ->assertJsonPath('data.unit_price', '49.99')
            ->assertJsonPath('data.subtotal', '99.98');

        $cartItemId = $cartItemResponse->json('data.id');

        $this->getJson('/api/cart-items')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->putJson("/api/cart-items/{$cartItemId}", [
            'quantity' => 3,
        ])->assertOk()
            ->assertJsonPath('data.subtotal', '149.97');

        $this->getJson("/api/carts/{$cartId}")
            ->assertOk()
            ->assertJsonPath('data.total_items', 3)
            ->assertJsonPath('data.total_amount', '149.97');

        $this->putJson("/api/carts/{$cartId}", [
            'status' => 'checked_out',
        ])->assertOk()
            ->assertJsonPath('data.status', 'checked_out');

        $this->deleteJson("/api/cart-items/{$cartItemId}")
            ->assertOk();

        $this->getJson("/api/carts/{$cartId}")
            ->assertOk()
            ->assertJsonPath('data.total_items', 0)
            ->assertJsonPath('data.total_amount', '0.00');

        $this->deleteJson("/api/carts/{$cartId}")
            ->assertOk();

        $this->assertDatabaseMissing('carts', ['id' => $cartId]);
    }

    public function test_authenticated_user_can_manage_orders_and_order_items(): void
    {
        $user = $this->actingUser();
        $product = $this->createProduct(25.50);

        $orderResponse = $this->postJson('/api/orders', [
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'notes' => 'First order',
        ]);

        $orderResponse->assertCreated()
            ->assertJsonPath('data.user_id', $user->id)
            ->assertJsonPath('data.total_items', 0)
            ->assertJsonPath('data.total_amount', '0.00');

        $orderId = $orderResponse->json('data.id');

        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.id', $orderId);

        $orderItemResponse = $this->postJson('/api/order-items', [
            'order_id' => $orderId,
            'product_id' => $product->id,
            'quantity' => 4,
        ]);

        $orderItemResponse->assertCreated()
            ->assertJsonPath('data.order_id', $orderId)
            ->assertJsonPath('data.product_name', $product->name)
            ->assertJsonPath('data.unit_price', '25.50')
            ->assertJsonPath('data.subtotal', '102.00');

        $orderItemId = $orderItemResponse->json('data.id');

        $this->getJson('/api/order-items')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/products/most-ordered')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.total_ordered', 4);

        $this->putJson("/api/order-items/{$orderItemId}", [
            'quantity' => 1,
        ])->assertOk()
            ->assertJsonPath('data.subtotal', '25.50');

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.total_items', 1)
            ->assertJsonPath('data.total_amount', '25.50');

        $this->putJson("/api/orders/{$orderId}", [
            'status' => 'completed',
            'payment_status' => 'paid',
        ])->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.payment_status', 'paid');

        $this->deleteJson("/api/order-items/{$orderItemId}")
            ->assertOk();

        $this->getJson("/api/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.total_items', 0)
            ->assertJsonPath('data.total_amount', '0.00');

        $this->deleteJson("/api/orders/{$orderId}")
            ->assertOk();

        $this->assertDatabaseMissing('orders', ['id' => $orderId]);
    }

    public function test_cart_item_rejects_client_controlled_price_and_product_name(): void
    {
        $this->actingUser();
        $product = $this->createProduct(49.99);

        $cartResponse = $this->postJson('/api/carts', [
            'status' => 'active',
        ])->assertCreated();

        $this->postJson('/api/cart-items', [
            'cart_id' => $cartResponse->json('data.id'),
            'product_id' => $product->id,
            'quantity' => 1,
            'product_name' => 'Tampered Product',
            'unit_price' => 0.01,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['product_name', 'unit_price']);
    }

    public function test_order_item_rejects_client_controlled_price_and_product_name(): void
    {
        $this->actingUser();
        $product = $this->createProduct(25.50);

        $orderResponse = $this->postJson('/api/orders', [
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ])->assertCreated();

        $this->postJson('/api/order-items', [
            'order_id' => $orderResponse->json('data.id'),
            'product_id' => $product->id,
            'quantity' => 1,
            'product_name' => 'Tampered Product',
            'unit_price' => 0.01,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['product_name', 'unit_price']);
    }

    public function test_admin_can_manage_inventory_movements(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'phone' => '966500000099',
            'password' => 'Admin12345',
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $product = $this->createProduct(30);

        $createResponse = $this->postJson('/api/admin/inventory-movements', [
            'product_id' => $product->id,
            'type' => 'sale',
            'quantity' => 2,
            'stock_before' => 10,
            'stock_after' => 8,
            'unit_price' => 30,
            'reason' => 'Checkout completed',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.total_price', '60.00');

        $movementId = $createResponse->json('data.id');

        $this->getJson('/api/admin/inventory-movements')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/admin/inventory-movements/{$movementId}")
            ->assertOk()
            ->assertJsonPath('data.id', $movementId);

        $this->putJson("/api/admin/inventory-movements/{$movementId}", [
            'quantity' => 1,
            'unit_price' => 35,
            'stock_before' => 8,
            'stock_after' => 7,
            'reason' => 'Adjusted movement',
        ])->assertOk()
            ->assertJsonPath('data.total_price', '35.00')
            ->assertJsonPath('data.reason', 'Adjusted movement');

        $this->deleteJson("/api/admin/inventory-movements/{$movementId}")
            ->assertOk();

        $this->assertDatabaseMissing('inventory_movements', ['id' => $movementId]);
    }

    private function actingUser(): User
    {
        $user = User::create([
            'name' => 'Normal User',
            'phone' => '966500000010',
            'password' => 'User12345',
            'role' => 'user',
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createProduct(float $price): Product
    {
        return Product::create([
            'name' => 'Commerce Product '.$price,
            'description' => 'Created for commerce CRUD tests',
            'price' => $price,
            'stock_quantity' => 100,
            'quantity_counter' => 100,
            'status' => 'active',
            'photos' => [],
        ]);
    }
}
