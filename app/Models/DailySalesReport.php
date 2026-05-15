<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySalesReport extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'report_date',
        'total_orders',
        'total_sales',
        'total_items_sold',
        'inventory_movements',
        'generated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'total_orders' => 'integer',
            'total_sales' => 'decimal:2',
            'total_items_sold' => 'integer',
            'inventory_movements' => 'integer',
            'generated_at' => 'datetime',
        ];
    }
}
