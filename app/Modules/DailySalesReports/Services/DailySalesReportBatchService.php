<?php

namespace App\Modules\DailySalesReports\Services;

use App\Jobs\GenerateDailySalesReportJob;
use App\Models\DailySalesReport;
use App\Models\DailySalesReportItem;
use App\Models\InventoryMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DailySalesReportBatchService
{
    public function queueForDate(string $reportDate): void
    {
        GenerateDailySalesReportJob::dispatch($this->normalizeDate($reportDate)->toDateString());
    }

    public function generateForDate(string $reportDate): DailySalesReport
    {
        $date = $this->normalizeDate($reportDate);

        $totals = [
            'order_ids' => [],
            'total_sales' => 0.0,
            'total_items_sold' => 0,
            'inventory_movements' => 0,
            'products' => [],
        ];

        InventoryMovement::query()
            ->where('type', 'sale')
            ->whereBetween('created_at', [$date->startOfDay(), $date->endOfDay()])
            ->orderBy('id')
            ->chunkById($this->chunkSize(), function ($movements) use (&$totals): void {
                foreach ($movements as $movement) {
                    if ($movement->order_id !== null) {
                        $totals['order_ids'][$movement->order_id] = true;
                    }

                    $movementTotal = $movement->total_price !== null
                        ? (float) $movement->total_price
                        : round((float) ($movement->unit_price ?? 0) * (int) $movement->quantity, 2);

                    $totals['total_sales'] += $movementTotal;
                    $totals['total_items_sold'] += (int) $movement->quantity;
                    $totals['inventory_movements']++;

                    if (! isset($totals['products'][$movement->product_id])) {
                        $totals['products'][$movement->product_id] = [
                            'product_id' => (int) $movement->product_id,
                            'total_quantity_sold' => 0,
                            'total_revenue' => 0.0,
                            'inventory_movements' => 0,
                        ];
                    }

                    $totals['products'][$movement->product_id]['total_quantity_sold'] += (int) $movement->quantity;
                    $totals['products'][$movement->product_id]['total_revenue'] += $movementTotal;
                    $totals['products'][$movement->product_id]['inventory_movements']++;
                }
            });

        /** @var array<int, array{product_id:int,total_quantity_sold:int,total_revenue:float,inventory_movements:int}> $productRows */
        $productRows = array_values($totals['products']);

        usort($productRows, function (array $left, array $right): int {
            $byRevenue = $right['total_revenue'] <=> $left['total_revenue'];

            if ($byRevenue !== 0) {
                return $byRevenue;
            }

            return $right['total_quantity_sold'] <=> $left['total_quantity_sold'];
        });

        return DB::transaction(function () use ($date, $totals, $productRows): DailySalesReport {
            $report = DailySalesReport::query()->updateOrCreate(
                ['report_date' => $date->toDateString()],
                [
                    'total_orders' => count($totals['order_ids']),
                    'total_sales' => round((float) $totals['total_sales'], 2),
                    'total_items_sold' => (int) $totals['total_items_sold'],
                    'inventory_movements' => (int) $totals['inventory_movements'],
                    'generated_at' => now(),
                ],
            );

            DailySalesReportItem::query()
                ->where('daily_sales_report_id', $report->id)
                ->delete();

            foreach ($productRows as $index => $productRow) {
                DailySalesReportItem::query()->create([
                    'daily_sales_report_id' => $report->id,
                    'product_id' => $productRow['product_id'],
                    'total_quantity_sold' => $productRow['total_quantity_sold'],
                    'total_revenue' => round($productRow['total_revenue'], 2),
                    'inventory_movements' => $productRow['inventory_movements'],
                    'product_rank' => $index + 1,
                ]);
            }

            return $report->fresh();
        });
    }

    private function normalizeDate(string $reportDate): CarbonImmutable
    {
        return CarbonImmutable::parse($reportDate);
    }

    private function chunkSize(): int
    {
        return max(1, (int) config('reports.daily_sales.chunk_size', 100));
    }
}
