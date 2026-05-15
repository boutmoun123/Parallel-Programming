<?php

namespace App\Modules\DailySalesReports\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailySalesReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'report_date' => $this->report_date?->toDateString(),
            'total_orders' => $this->total_orders,
            'total_sales' => $this->total_sales,
            'total_items_sold' => $this->total_items_sold,
            'inventory_movements' => $this->inventory_movements,
            'generated_at' => $this->generated_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
