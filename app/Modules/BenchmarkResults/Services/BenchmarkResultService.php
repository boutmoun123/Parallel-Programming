<?php

namespace App\Modules\BenchmarkResults\Services;

use App\Models\BenchmarkResult;

class BenchmarkResultService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createBenchmarkResult(array $data): BenchmarkResult
    {
        if (! array_key_exists('total_requests', $data)) {
            $data['total_requests'] = ($data['successful_requests'] ?? 0) + ($data['failed_requests'] ?? 0);
        }

        $data['tested_at'] ??= now();

        return BenchmarkResult::create($data);
    }
}
