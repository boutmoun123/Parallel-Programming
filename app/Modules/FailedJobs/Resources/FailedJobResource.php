<?php

namespace App\Modules\FailedJobs\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FailedJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'connection' => $this->connection,
            'queue' => $this->queue,
            'exception' => $this->exception,
            'failed_at' => $this->failed_at?->toISOString(),
        ];
    }
}
