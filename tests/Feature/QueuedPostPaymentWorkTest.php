<?php

namespace Tests\Feature;

use App\Jobs\IssueOrderInvoiceJob;
use App\Jobs\SendPaymentSuccessNotificationJob;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QueuedPostPaymentWorkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['wallets.payment_delay_seconds' => 0]);
    }

    public function test_checkout_dispatches_invoice_and_notification_jobs(): void
    {
        Queue::fake();

        $user = $this->actingUser('966500000501');
        $this->fundWallet($user, 500);
        $product = $this->product();
        $order = $this->orderForUser($user, 'ORD-QUEUE-DISPATCH');

        $this->postJson('/api/order-items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 75.25,
        ])->assertCreated();

        $this->postJson("/api/orders/{$order->id}/checkout", [
            'payment_method' => 'card',
            'idempotency_key' => 'queued-post-payment-dispatch',
        ])->assertOk();

        Queue::assertPushed(IssueOrderInvoiceJob::class, fn (IssueOrderInvoiceJob $job): bool => $job->orderId === $order->id);
        Queue::assertPushed(SendPaymentSuccessNotificationJob::class, fn (SendPaymentSuccessNotificationJob $job): bool => $job->orderId === $order->id);
    }

    public function test_sync_queue_executes_invoice_and_notification_jobs_after_checkout(): void
    {
        $user = $this->actingUser('966500000502');
        $this->fundWallet($user, 500);
        $product = $this->product();
        $order = $this->orderForUser($user, 'ORD-QUEUE-SYNC');

        $this->postJson('/api/order-items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 75.25,
        ])->assertCreated();

        $this->postJson("/api/orders/{$order->id}/checkout", [
            'payment_method' => 'card',
            'idempotency_key' => 'queued-post-payment-sync',
        ])->assertOk();

        $this->assertDatabaseHas('invoices', [
            'order_id' => $order->id,
            'status' => Invoice::STATUS_ISSUED,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => Notification::TYPE_PAYMENT_SUCCESS,
            'status' => Notification::STATUS_SENT,
        ]);
    }

    private function actingUser(string $phone): User
    {
        $user = User::create([
            'name' => 'Queued User '.$phone,
            'phone' => $phone,
            'password' => 'User12345',
            'role' => 'user',
        ]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function orderForUser(User $user, string $orderNumber): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'order_number' => $orderNumber,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'total_items' => 0,
            'total_amount' => 0,
        ]);
    }

    private function product(): Product
    {
        return Product::create([
            'name' => 'Queued Product',
            'description' => 'Used for queued post-payment tests',
            'price' => 75.25,
            'stock_quantity' => 5,
            'quantity_counter' => 5,
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
