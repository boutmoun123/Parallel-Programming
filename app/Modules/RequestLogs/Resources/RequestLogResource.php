<?php

namespace App\Modules\RequestLogs\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'server_node_id' => $this->server_node_id,
            'user_id' => $this->user_id,
            'operation_name' => $this->operation_name,
            'endpoint' => $this->endpoint,
            'method' => $this->method,
            'response_time_ms' => $this->response_time_ms,
            'status_code' => $this->status_code,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
