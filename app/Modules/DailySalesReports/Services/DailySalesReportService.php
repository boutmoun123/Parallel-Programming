<?php

namespace App\Modules\DailySalesReports\Services;

use App\Models\DailySalesReport;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class DailySalesReportService
{
    private const CACHE_TTL_SECONDS = 60;

    public const LATEST_REPORTS_CACHE_KEY = 'daily-sales-reports:latest';

    /**
     * @return array{data: Collection<int, DailySalesReport>, source: string}
     */
    public function getLatestDailySalesReports(): array
    {
        $cache = Cache::store(config('cache.default'));
        $cacheKey = self::LATEST_REPORTS_CACHE_KEY;

        if ($cache->has($cacheKey)) {
            $ids = $cache->get($cacheKey);
            $source = 'cache';
        } else {
            $ids = DailySalesReport::query()
                ->latest()
                ->limit(20)
                ->pluck('id')
                ->all();

            $cache->put($cacheKey, $ids, self::CACHE_TTL_SECONDS);

            $source = 'database';
        }

        $reports = DailySalesReport::query()
            ->whereIn('id', $ids)
            ->latest()
            ->get();

        return [
            'data' => $reports,
            'source' => $source,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createDailySalesReport(array $data): DailySalesReport
    {
        $data['generated_at'] ??= now();
        $data['total_orders'] ??= 0;
        $data['total_sales'] ??= 0;
        $data['total_items_sold'] ??= 0;

        $report = DailySalesReport::create($data);

        self::forgetLatestReportsCache();

        return $report;
    }

    public static function forgetLatestReportsCache(): void
    {
        Cache::store(config('cache.default'))->forget(self::LATEST_REPORTS_CACHE_KEY);
    }
}
