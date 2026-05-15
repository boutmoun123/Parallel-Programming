<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RequestLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'server_node_id',
        'user_id',
        'operation_name',
        'endpoint',
        'method',
        'response_time_ms',
        'status_code',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'server_node_id' => 'integer',
            'user_id' => 'integer',
            'response_time_ms' => 'integer',
            'status_code' => 'integer',
        ];
    }
}
