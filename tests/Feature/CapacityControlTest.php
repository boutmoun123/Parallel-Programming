<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use App\Modules\Infrastructure\Data\CapacityReservation;
use App\Modules\Infrastructure\Services\CapacityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CapacityControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['wallets.payment_delay_seconds' => 0]);
    }

    public function test_admin_quantity_update_is_rejected_when_critical_capacity_is_exhausted(): void
    {
        config(['capacity.groups.critical-operations.limit' => 1]);

        $reservation = app(CapacityService::class)->acquire('critical-operations');
        $this->assertInstanceOf(CapacityReservation::class, $reservation);

        $admin = User::create([
            'name' => 'Capacity Admin',
            'phone' => '966500000401',
            'password' => 'Admin12345',
            'role' => 'admin',
        ]);

        Sanctum::actingAs($admin);

        $product = Product::create([
            'name' => 'Capacity Product',
            'description' => 'Used for capacity control tests',
            'price' => 10,
            'stock_quantity' => 5,
            'quantity_counter' => 5,
            'status' => 'active',
            'photos' => [],
        ]);

        try {
            $this->patchJson("/api/admin/products/{$product->id}/quantity", [
                'stock_quantity' => 8,
            ])->assertStatus(503)
                ->assertJsonPath('message', 'System is temporarily at capacity for this operation.')
                ->assertJsonPath('meta.group', 'critical-operations')
                ->assertJsonPath('meta.limit', 1)
                ->assertHeader('Retry-After', '2');
        } finally {
            app(CapacityService::class)->release($reservation);
        }
    }

    public function test_checkout_capacity_slot_is_released_after_request_completes(): void
    {
        config(['capacity.groups.checkout.limit' => 1]);

        $user = User::create([
            'name' => 'Capacity Checkout User',
            'phone' => '966500000402',
            'password' => 'User12345',
            'role' => 'user',
        ]);

        Sanctum::actingAs($user);
        Wallet::create([
            'user_id' => $user->id,
            'balance' => 500,
        ]);

        $product = Product::create([
            'name' => 'Checkout Capacity Product',
            'description' => 'Used for checkout capacity control tests',
            'price' => 99.99,
            'stock_quantity' => 2,
            'quantity_counter' => 2,
            'status' => 'active',
            'photos' => [],
        ]);

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-CAP-'.str()->random(6),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_items' => 0,
            'total_amount' => 0,
        ]);

        $this->postJson('/api/order-items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 99.99,
        ])->assertCreated();

        $this->postJson("/api/orders/{$order->id}/checkout", [
            'payment_method' => 'card',
            'idempotency_key' => 'capacity-checkout-1',
        ])->assertOk()
            ->assertHeader('X-Capacity-Group', 'checkout');

        $snapshot = app(CapacityService::class)->snapshot('checkout');

        $this->assertSame(0, $snapshot['active']);
        $this->assertSame(1, $snapshot['remaining']);
    }
}
