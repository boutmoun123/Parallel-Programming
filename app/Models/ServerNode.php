<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServerNode extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_OVERLOADED = 'overloaded';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'host',
        'status',
        'max_concurrent_requests',
        'current_load',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'max_concurrent_requests' => 'integer',
            'current_load' => 'integer',
        ];
    }
}
