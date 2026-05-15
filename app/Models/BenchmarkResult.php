<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BenchmarkResult extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'operation_name',
        'scenario',
        'concurrent_users',
        'total_requests',
        'successful_requests',
        'failed_requests',
        'average_response_time_ms',
        'max_response_time_ms',
        'throughput_per_second',
        'bottleneck_note',
        'optimization_applied',
        'tested_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'concurrent_users' => 'integer',
            'total_requests' => 'integer',
            'successful_requests' => 'integer',
            'failed_requests' => 'integer',
            'average_response_time_ms' => 'integer',
            'max_response_time_ms' => 'integer',
            'throughput_per_second' => 'decimal:2',
            'tested_at' => 'datetime',
        ];
    }
}
