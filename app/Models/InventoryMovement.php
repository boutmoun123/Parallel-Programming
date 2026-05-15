<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'product_id',
        'order_id',
        'order_item_id',
        'type',
        'quantity',
        'stock_before',
        'stock_after',
        'unit_price',
        'total_price',
        'reason',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'order_id' => 'integer',
            'order_item_id' => 'integer',
            'quantity' => 'integer',
            'stock_before' => 'integer',
            'stock_after' => 'integer',
            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }
}
