<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySalesReportItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'daily_sales_report_id',
        'product_id',
        'total_quantity_sold',
        'total_revenue',
        'inventory_movements',
        'product_rank',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'daily_sales_report_id' => 'integer',
            'product_id' => 'integer',
            'total_quantity_sold' => 'integer',
            'total_revenue' => 'decimal:2',
            'inventory_movements' => 'integer',
            'product_rank' => 'integer',
        ];
    }
}
