<?php

namespace Tests\Feature;

use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderCheckoutConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['wallets.payment_delay_seconds' => 0]);
    }

    public function test_checkout_updates_order_payment_and_inventory_atomically(): void
    {
        $user = $this->actingUser('966500000201');
        $this->fundWallet($user, 500);
        $product = $this->product(stockQuantity: 5, quantityCounter: 5);
        $order = $this->orderForUser($user);

        $this->postJson('/api/order-items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
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
        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => 259,
        ]);
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
        $this->fundWallet($firstUser, 500);
        $firstOrder = $this->orderForUser($firstUser);

        $this->postJson('/api/order-items', [
            'order_id' => $firstOrder->id,
            'product_id' => $product->id,
            'quantity' => 2,
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
        $this->fundWallet($secondUser, 500);

        $secondOrder = $this->orderForUser($secondUser);

        $this->postJson('/api/order-items', [
            'order_id' => $secondOrder->id,
            'product_id' => $product->id,
            'quantity' => 2,
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

    public function test_checkout_fails_when_wallet_balance_is_insufficient_without_decreasing_stock(): void
    {
        $user = $this->actingUser('966500000401');
        $this->fundWallet($user, 50);
        $product = $this->product(stockQuantity: 5, quantityCounter: 5);
        $order = $this->orderForUser($user);

        $this->postJson('/api/order-items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ])->assertCreated();

        $response = $this->postJson("/api/orders/{$order->id}/checkout", [
            'payment_method' => 'wallet',
            'idempotency_key' => 'checkout-wallet-insufficient',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('message', 'Insufficient wallet balance.');

        $product->refresh();
        $order->refresh();

        $this->assertSame(5, $product->stock_quantity);
        $this->assertSame(5, $product->quantity_counter);
        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertDatabaseHas('wallets', [
            'user_id' => $user->id,
            'balance' => 50,
        ]);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_duplicate_idempotency_key_is_rejected_without_second_payment_or_stock_decrement(): void
    {
        $product = $this->product(stockQuantity: 5, quantityCounter: 5);
        $firstUser = $this->actingUser('966500000403');
        $this->fundWallet($firstUser, 500);
        $firstOrder = $this->orderForUser($firstUser);

        $this->postJson('/api/order-items', [
            'order_id' => $firstOrder->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson("/api/orders/{$firstOrder->id}/checkout", [
            'payment_method' => 'wallet',
            'idempotency_key' => 'duplicate-idempotency-proof',
        ])->assertOk();

        $secondUser = User::create([
            'name' => 'Duplicate Idempotency User',
            'phone' => '966500000404',
            'password' => 'User12345',
            'role' => 'user',
        ]);

        Sanctum::actingAs($secondUser);
        $this->fundWallet($secondUser, 500);
        $secondOrder = $this->orderForUser($secondUser);

        $this->postJson('/api/order-items', [
            'order_id' => $secondOrder->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson("/api/orders/{$secondOrder->id}/checkout", [
            'payment_method' => 'wallet',
            'idempotency_key' => 'duplicate-idempotency-proof',
        ])->assertStatus(409)
            ->assertJsonPath('message', 'Idempotency key has already been used.');

        $product->refresh();
        $secondOrder->refresh();

        $this->assertSame(4, $product->stock_quantity);
        $this->assertSame(4, $product->quantity_counter);
        $this->assertSame('pending', $secondOrder->status);
        $this->assertSame('unpaid', $secondOrder->payment_status);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('inventory_movements', 1);
    }

    public function test_standalone_payment_creation_is_rejected(): void
    {
        $this->actingUser('966500000402');

        $this->postJson('/api/payments', [
            'payment_method' => 'wallet',
            'idempotency_key' => 'standalone-payment-rejected',
            'amount' => 120.50,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'Payments must be created through checkout.');

        $this->assertDatabaseCount('payments', 0);
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

    private function fundWallet(User $user, float $balance): Wallet
    {
        return Wallet::create([
            'user_id' => $user->id,
            'balance' => $balance,
        ]);
    }
}
