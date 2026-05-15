<?php

namespace App\Modules\DailySalesReportItems\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DailySalesReportItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'daily_sales_report_id' => $this->daily_sales_report_id,
            'product_id' => $this->product_id,
            'total_quantity_sold' => $this->total_quantity_sold,
            'total_revenue' => $this->total_revenue,
            'inventory_movements' => $this->inventory_movements,
            'product_rank' => $this->product_rank,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
