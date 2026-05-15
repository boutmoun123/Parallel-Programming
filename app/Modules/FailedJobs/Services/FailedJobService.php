<?php

namespace App\Modules\FailedJobs\Services;

use App\Models\FailedJob;
use Illuminate\Database\Eloquent\Collection;

class FailedJobService
{
    /**
     * @return Collection<int, FailedJob>
     */
    public function getLatestFailedJobs(): Collection
    {
        return FailedJob::query()
            ->orderByDesc('failed_at')
            ->get();
    }

    public function getFailedJobById(FailedJob $failedJob): FailedJob
    {
        return $failedJob;
    }
}
