<?php

namespace App\Modules\DailySalesReports\Services;

use App\Models\DailySalesReport;
use Illuminate\Database\Eloquent\Collection;

class DailySalesReportService
{
    /**
     * @return Collection<int, DailySalesReport>
     */
    public function getLatestDailySalesReports(): Collection
    {
        return DailySalesReport::query()
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDailySalesReport(array $data): DailySalesReport
    {
        $data['generated_at'] ??= now();
        $data['total_orders'] ??= 0;
        $data['total_sales'] ??= 0;
        $data['total_items_sold'] ??= 0;

        return DailySalesReport::create($data);
    }
}
