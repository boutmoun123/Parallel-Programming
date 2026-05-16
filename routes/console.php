<?php

use App\Jobs\GenerateDailySalesReportJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reports:generate-daily-sales {date?}', function (?string $date = null) {
    $reportDate = $date ?? now()->toDateString();

    GenerateDailySalesReportJob::dispatch($reportDate);

    $this->info("Daily sales report job dispatched for {$reportDate}");
})->purpose('Dispatch a queued job that generates the daily sales report in chunks');

Schedule::call(function (): void {
    GenerateDailySalesReportJob::dispatch(now()->toDateString());
})->dailyAt('23:55');
