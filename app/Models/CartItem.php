<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_name',
        'quantity',
        'unit_price',
        'subtotal',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cart_id' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }
}
