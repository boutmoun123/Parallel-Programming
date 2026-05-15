<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'cart_id',
        'order_number',
        'status',
        'payment_status',
        'total_items',
        'total_amount',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'cart_id' => 'integer',
            'total_items' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }
}
