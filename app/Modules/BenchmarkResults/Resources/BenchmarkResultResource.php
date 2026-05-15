<?php

namespace App\Modules\BenchmarkResults\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BenchmarkResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operation_name' => $this->operation_name,
            'scenario' => $this->scenario,
            'concurrent_users' => $this->concurrent_users,
            'total_requests' => $this->total_requests,
            'successful_requests' => $this->successful_requests,
            'failed_requests' => $this->failed_requests,
            'average_response_time_ms' => $this->average_response_time_ms,
            'max_response_time_ms' => $this->max_response_time_ms,
            'throughput_per_second' => $this->throughput_per_second,
            'bottleneck_note' => $this->bottleneck_note,
            'optimization_applied' => $this->optimization_applied,
            'tested_at' => $this->tested_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
