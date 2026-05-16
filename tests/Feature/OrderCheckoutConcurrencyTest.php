<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCheckoutConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_updates_order_payment_and_inventory_atomically(): void
    {
        $user = $this->actingUser('966500000201');
        $product = $this->product(stockQuantity: 5, quantityCounter: 5);
        $order = $this->orderForUser($user);

        $this->postJson('/api/order-items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 120.50,
        ])->assertCreated();

        $response = $this->postJson("/api/orders/{$order->id}/checkout", [
            'payment_method' => 'card',
            'idempotency_key' => 'checkout-order-1',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.order.id', $order->id)
            ->assertJsonPath('data.order.status', 'completed')
            ->assertJsonPath('data.order.payment_status', 'paid')
            ->assertJsonPath('data.payment.order_id', $order->id)
            ->assertJsonPath('data.payment.amount', '241.00')
            ->assertJsonCount(1, 'data.inventory_movements');

        $product->refresh();
        $order->refresh();

        $this->assertSame(3, $product->stock_quantity);
        $this->assertSame(3, $product->quantity_counter);
        $this->assertSame('completed', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('inventory_movements', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'stock_before' => 5,
            'stock_after' => 3,
        ]);
    }

    public function test_second_checkout_fails_when_first_order_consumes_shared_stock(): void
    {
        $product = $this->product(stockQuantity: 3, quantityCounter: 3);
        $firstUser = $this->actingUser('966500000301');
        $firstOrder = $this->orderForUser($firstUser);

        $this->postJson('/api/order-items', [
            'order_id' => $firstOrder->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 120.50,
        ])->assertCreated();

        $this->postJson("/api/orders/{$firstOrder->id}/checkout", [
            'payment_method' => 'card',
            'idempotency_key' => 'checkout-shared-stock-1',
        ])->assertOk();

        $secondUser = User::create([
            'name' => 'Second User',
            'phone' => '966500000302',
            'password' => 'User12345',
            'role' => 'user',
        ]);

        Sanctum::actingAs($secondUser);

        $secondOrder = $this->orderForUser($secondUser);

        $this->postJson('/api/order-items', [
            'order_id' => $secondOrder->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 120.50,
        ])->assertCreated();

        $response = $this->postJson("/api/orders/{$secondOrder->id}/checkout", [
            'payment_method' => 'card',
            'idempotency_key' => 'checkout-shared-stock-2',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Requested quantity is no longer available.');

        $product->refresh();
        $secondOrder->refresh();

        $this->assertSame(1, $product->stock_quantity);
        $this->assertSame(1, $product->quantity_counter);
        $this->assertSame('pending', $secondOrder->status);
        $this->assertSame('unpaid', $secondOrder->payment_status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, InventoryMovement::query()->count());
    }

    private function actingUser(string $phone): User
    {
        $user = User::create([
            'name' => 'Checkout User '.$phone,
            'phone' => $phone,
            'password' => 'User12345',
            'role' => 'user',
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function orderForUser(User $user): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-'.$user->id.'-'.str()->random(6),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_items' => 0,
            'total_amount' => 0,
        ]);
    }

    private function product(int $stockQuantity, int $quantityCounter): Product
    {
        return Product::create([
            'name' => 'Checkout Product',
            'description' => 'Used in checkout concurrency tests',
            'price' => 120.50,
            'stock_quantity' => $stockQuantity,
            'quantity_counter' => $quantityCounter,
            'status' => 'active',
            'photos' => [],
        ]);
    }
}
