<?php

namespace Tests\Feature;

use App\Jobs\GenerateDailySalesReportJob;
use App\Models\DailySalesReport;
use App\Models\DailySalesReportItem;
use App\Models\InventoryMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailySalesReportBatchProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_endpoint_dispatches_background_job(): void
    {
        Queue::fake();

        Sanctum::actingAs($this->user('966500000601'));

        $this->postJson('/api/daily-sales-reports/generate', [
            'report_date' => '2026-05-16',
        ])->assertStatus(202)
            ->assertJsonPath('data.status', 'queued');

        Queue::assertPushed(GenerateDailySalesReportJob::class, fn (GenerateDailySalesReportJob $job): bool => $job->reportDate === '2026-05-16');
    }

    public function test_artisan_command_dispatches_daily_sales_report_job(): void
    {
        Queue::fake();

        Artisan::call('reports:generate-daily-sales', [
            'date' => '2026-05-17',
        ]);

        Queue::assertPushed(GenerateDailySalesReportJob::class, fn (GenerateDailySalesReportJob $job): bool => $job->reportDate === '2026-05-17');
    }

    public function test_job_generates_daily_sales_report_in_chunks_from_sale_movements(): void
    {
        config(['reports.daily_sales.chunk_size' => 2]);

        InventoryMovement::query()->create([
            'product_id' => 1,
            'order_id' => 1001,
            'order_item_id' => 5001,
            'type' => 'sale',
            'quantity' => 2,
            'stock_before' => 10,
            'stock_after' => 8,
            'unit_price' => 50,
            'total_price' => 100,
            'reason' => 'Sale A',
            'created_at' => '2026-05-18 09:00:00',
        ]);

        InventoryMovement::query()->create([
            'product_id' => 2,
            'order_id' => 1002,
            'order_item_id' => 5002,
            'type' => 'sale',
            'quantity' => 1,
            'stock_before' => 7,
            'stock_after' => 6,
            'unit_price' => 80,
            'total_price' => 80,
            'reason' => 'Sale B',
            'created_at' => '2026-05-18 10:00:00',
        ]);

        InventoryMovement::query()->create([
            'product_id' => 1,
            'order_id' => 1003,
            'order_item_id' => 5003,
            'type' => 'sale',
            'quantity' => 3,
            'stock_before' => 8,
            'stock_after' => 5,
            'unit_price' => 50,
            'total_price' => 150,
            'reason' => 'Sale C',
            'created_at' => '2026-05-18 11:00:00',
        ]);

        InventoryMovement::query()->create([
            'product_id' => 3,
            'order_id' => 1004,
            'order_item_id' => 5004,
            'type' => 'restock',
            'quantity' => 4,
            'stock_before' => 5,
            'stock_after' => 9,
            'unit_price' => 20,
            'total_price' => 80,
            'reason' => 'Restock',
            'created_at' => '2026-05-18 12:00:00',
        ]);

        InventoryMovement::query()->create([
            'product_id' => 2,
            'order_id' => 1005,
            'order_item_id' => 5005,
            'type' => 'sale',
            'quantity' => 2,
            'stock_before' => 6,
            'stock_after' => 4,
            'unit_price' => 80,
            'total_price' => 160,
            'reason' => 'Sale D',
            'created_at' => '2026-05-19 09:00:00',
        ]);

        GenerateDailySalesReportJob::dispatchSync('2026-05-18');

        $report = DailySalesReport::query()
            ->whereDate('report_date', '2026-05-18')
            ->firstOrFail();

        $this->assertSame(3, $report->total_orders);
        $this->assertSame('330.00', $report->total_sales);
        $this->assertSame(6, $report->total_items_sold);
        $this->assertSame(3, $report->inventory_movements);

        $items = DailySalesReportItem::query()
            ->where('daily_sales_report_id', $report->id)
            ->orderBy('product_rank')
            ->get();

        $this->assertCount(2, $items);
        $this->assertSame(1, $items[0]->product_id);
        $this->assertSame(5, $items[0]->total_quantity_sold);
        $this->assertSame('250.00', $items[0]->total_revenue);
        $this->assertSame(1, $items[0]->product_rank);

        $this->assertSame(2, $items[1]->product_id);
        $this->assertSame(1, $items[1]->total_quantity_sold);
        $this->assertSame('80.00', $items[1]->total_revenue);
        $this->assertSame(2, $items[1]->product_rank);
    }

    private function user(string $phone): User
    {
        return User::create([
            'name' => 'Report User '.$phone,
            'phone' => $phone,
            'password' => 'User12345',
            'role' => 'user',
        ]);
    }
}
