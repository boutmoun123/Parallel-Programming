<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'status',
        'total_items',
        'total_amount',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'total_items' => 'integer',
            'total_amount' => 'decimal:2',
        ];
    }
}
