<?php

namespace App\Modules\RequestLogs\Services;

use App\Models\RequestLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class RequestLogService
{
    /**
     * @return Collection<int, RequestLog>
     */
    public function getLatestRequestLogs(): Collection
    {
        return RequestLog::query()
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRequestLog(array $data): RequestLog
    {
        $data['method'] = Str::upper($data['method']);

        return RequestLog::create($data);
    }
}
