<?php

namespace App\Jobs;

use App\Modules\DailySalesReports\Services\DailySalesReportBatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateDailySalesReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $reportDate)
    {
        $this->afterCommit();
    }

    public function handle(DailySalesReportBatchService $dailySalesReportBatchService): void
    {
        $dailySalesReportBatchService->generateForDate($this->reportDate);
    }
}
