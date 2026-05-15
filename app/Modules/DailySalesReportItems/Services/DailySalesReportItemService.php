<?php

namespace App\Modules\DailySalesReportItems\Services;

use App\Models\DailySalesReportItem;
use Illuminate\Database\Eloquent\Collection;

class DailySalesReportItemService
{
    /**
     * @return Collection<int, DailySalesReportItem>
     */
    public function getLatestDailySalesReportItems(): Collection
    {
        return DailySalesReportItem::query()
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDailySalesReportItem(array $data): DailySalesReportItem
    {
        $data['total_quantity_sold'] ??= 0;
        $data['total_revenue'] ??= 0;

        return DailySalesReportItem::create($data);
    }
}
