<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NonFunctionalStructureTest extends TestCase
{
    /**
     * يرجع المسار الكامل للبحث داخل المشروع.
     */
    private function projectPath(string $path): string
    {
        return base_path(str_replace('/', DIRECTORY_SEPARATOR, $path));
    }

    /**
     * يبحث داخل ملفات PHP في مجلد معين عن كلمات محددة.
     */
    private function folderContainsAny(string $folder, array $needles): bool
    {
        $path = $this->projectPath($folder);

        if (! is_dir($path)) {
            return false;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path)
        );

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $content = file_get_contents($file->getRealPath());

            foreach ($needles as $needle) {
                if (str_contains($content, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * يجلب Route حسب Method و URI.
     */
    private function findRoute(string $method, string $uri)
    {
        foreach (Route::getRoutes() as $route) {
            if (! in_array(strtoupper($method), $route->methods(), true)) {
                continue;
            }

            $routeUri = $route->uri();

            if ($routeUri === $uri || $routeUri === 'api/' . ltrim($uri, '/')) {
                return $route;
            }
        }

        return null;
    }

    /**
     * يفحص أن Route معين موجود.
     */
    private function assertRouteExists(string $method, string $uri): void
    {
        $route = $this->findRoute($method, $uri);

        $this->assertNotNull(
            $route,
            "Route غير موجود: {$method} {$uri}"
        );
    }

    /**
     * يفحص أن Route معين عليه Middleware محدد.
     */
    private function assertRouteHasMiddleware(string $method, string $uri, string $middleware): void
    {
        $route = $this->findRoute($method, $uri);

        $this->assertNotNull(
            $route,
            "Route غير موجود: {$method} {$uri}"
        );

        $middlewares = $route->gatherMiddleware();

        $this->assertTrue(
            collect($middlewares)->contains(fn ($item) => $item === $middleware || str_starts_with($item, $middleware)),
            "Route {$method} {$uri} لا يحتوي على Middleware: {$middleware}. الموجود حاليا: " . implode(', ', $middlewares)
        );
    }

    /**
     * المتطلب 1:
     * حماية البيانات المشتركة من التضارب.
     *
     * المطلوب وجود مسار Checkout، ووجود إشارات داخل الكود إلى Transaction أو Locking.
     */
    public function test_requirement_1_concurrent_access_and_race_condition_protection_exists(): void
    {
        $this->assertRouteExists('POST', 'orders/{order}/checkout');

        $this->assertRouteHasMiddleware(
            'POST',
            'orders/{order}/checkout',
            'capacity:checkout'
        );

        $hasTransactionOrLocking =
            $this->folderContainsAny('app/Modules/Orders', [
                'DB::transaction',
                'databaseTransaction',
                'transaction(',
                'lockForUpdate',
                'sharedLock',
            ])
            ||
            $this->folderContainsAny('app/Modules/Products', [
                'DB::transaction',
                'databaseTransaction',
                'transaction(',
                'lockForUpdate',
                'sharedLock',
            ])
            ||
            $this->folderContainsAny('app/Modules/InventoryMovements', [
                'DB::transaction',
                'databaseTransaction',
                'transaction(',
                'lockForUpdate',
                'sharedLock',
            ]);

        $this->assertTrue(
            $hasTransactionOrLocking,
            'المتطلب 1 غير مثبت بالكود: لم أجد DB::transaction أو lockForUpdate داخل Orders أو Products أو InventoryMovements.'
        );
    }

    /**
     * المتطلب 2:
     * إدارة الموارد والتحكم بالسعة.
     *
     * المطلوب وجود Middleware capacity على العمليات الحرجة.
     */
    public function test_requirement_2_capacity_control_middleware_exists_on_critical_routes(): void
    {
        $criticalRoutes = [
            ['POST', 'payments'],
            ['POST', 'invoices'],
            ['POST', 'notifications'],
            ['POST', 'benchmark-results'],
            ['POST', 'server-nodes'],
            ['PUT', 'server-nodes/{serverNode}'],
            ['POST', 'request-logs'],
            ['POST', 'daily-sales-reports/generate'],
            ['POST', 'daily-sales-reports'],
            ['POST', 'daily-sales-report-items'],
            ['POST', 'carts'],
            ['PUT', 'carts/{cart}'],
            ['DELETE', 'carts/{cart}'],
            ['POST', 'cart-items'],
            ['PUT', 'cart-items/{cartItem}'],
            ['DELETE', 'cart-items/{cartItem}'],
            ['POST', 'orders'],
            ['POST', 'orders/{order}/checkout'],
            ['PUT', 'orders/{order}'],
            ['DELETE', 'orders/{order}'],
            ['POST', 'order-items'],
            ['PUT', 'order-items/{orderItem}'],
            ['DELETE', 'order-items/{orderItem}'],
            ['POST', 'admin/products'],
            ['PUT', 'admin/products/{product}'],
            ['DELETE', 'admin/products/{product}'],
            ['PATCH', 'admin/products/{product}/quantity'],
            ['POST', 'admin/inventory-movements'],
            ['PUT', 'admin/inventory-movements/{inventoryMovement}'],
            ['DELETE', 'admin/inventory-movements/{inventoryMovement}'],
        ];

        foreach ($criticalRoutes as [$method, $uri]) {
            $expectedMiddleware = $uri === 'orders/{order}/checkout'
                ? 'capacity:checkout'
                : 'capacity:critical-operations';

            $this->assertRouteHasMiddleware($method, $uri, $expectedMiddleware);
        }
    }

    /**
     * المتطلب 3:
     * المعالجة غير المتزامنة Queues.
     *
     * المطلوب وجود Jobs، ووجود ShouldQueue أو dispatch داخل الكود.
     */
    public function test_requirement_3_asynchronous_queues_exist(): void
    {
        $jobsPath = $this->projectPath('app/Jobs');

        $this->assertTrue(
            is_dir($jobsPath),
            'مجلد app/Jobs غير موجود. يجب وجود Jobs للمعالجة غير المتزامنة.'
        );

        $hasQueueCode =
            $this->folderContainsAny('app/Jobs', [
                'ShouldQueue',
                'Queueable',
                'Dispatchable',
            ])
            ||
            $this->folderContainsAny('app/Modules', [
                '::dispatch',
                'dispatch(',
                'dispatchSync',
                'onQueue',
            ]);

        $this->assertTrue(
            $hasQueueCode,
            'المتطلب 3 غير مثبت بالكود: لم أجد ShouldQueue أو dispatch أو onQueue داخل app/Jobs أو app/Modules.'
        );

        $this->assertRouteExists('POST', 'daily-sales-reports/generate');
    }

    /**
     * المتطلب 4:
     * معالجة البيانات الضخمة على دفعات.
     *
     * المطلوب وجود chunk أو chunkById أو lazyById داخل DailySalesReports.
     */
    public function test_requirement_4_batch_processing_with_chunks_exists(): void
    {
        $this->assertTrue(
            is_dir($this->projectPath('app/Modules/DailySalesReports')),
            'مجلد DailySalesReports غير موجود.'
        );

        $hasChunkProcessing = $this->folderContainsAny('app/Modules/DailySalesReports', [
            'chunk(',
            'chunkById(',
            'lazy(',
            'lazyById(',
            'cursor(',
        ]);

        $this->assertTrue(
            $hasChunkProcessing,
            'المتطلب 4 غير مثبت بالكود: لم أجد chunk أو chunkById أو lazyById أو cursor داخل DailySalesReports.'
        );

        $this->assertRouteExists('POST', 'daily-sales-reports/generate');
    }

    /**
     * المتطلب 5:
     * توزيع الأحمال.
     *
     * المطلوب وجود Middleware load-balance و Modules خاصة بالـ ServerNodes و RequestLogs.
     */
    public function test_requirement_5_load_distribution_exists(): void
    {
        $this->assertTrue(
            is_dir($this->projectPath('app/Modules/ServerNodes')),
            'مجلد ServerNodes غير موجود. هذا ضروري لمحاكاة الخوادم.'
        );

        $this->assertTrue(
            is_dir($this->projectPath('app/Modules/RequestLogs')),
            'مجلد RequestLogs غير موجود. هذا ضروري لتسجيل توزيع الطلبات.'
        );

        $this->assertRouteExists('GET', 'server-nodes');
        $this->assertRouteExists('POST', 'server-nodes');
        $this->assertRouteExists('GET', 'request-logs');
        $this->assertRouteExists('POST', 'request-logs');

        $routesThatShouldUseLoadBalance = [
            ['GET', 'products'],
            ['GET', 'products/most-ordered'],
            ['POST', 'auth/login'],
            ['POST', 'daily-sales-reports/generate'],
            ['POST', 'orders/{order}/checkout'],
            ['GET', 'server-nodes'],
            ['GET', 'request-logs'],
        ];

        foreach ($routesThatShouldUseLoadBalance as [$method, $uri]) {
            $this->assertRouteHasMiddleware($method, $uri, 'load-balance');
        }
    }

    /**
     * اختبار إضافي:
     * يتأكد أن مجلدات أول خمس متطلبات موجودة في Modules.
     */
    public function test_required_modules_for_first_five_non_functional_requirements_exist(): void
    {
        $requiredModules = [
            'app/Modules/Products',
            'app/Modules/Orders',
            'app/Modules/OrderItems',
            'app/Modules/InventoryMovements',
            'app/Modules/Payments',
            'app/Modules/DailySalesReports',
            'app/Modules/DailySalesReportItems',
            'app/Modules/ServerNodes',
            'app/Modules/RequestLogs',
            'app/Modules/BenchmarkResults',
            'app/Modules/Infrastructure',
        ];

        foreach ($requiredModules as $modulePath) {
            $this->assertTrue(
                is_dir($this->projectPath($modulePath)),
                "المجلد غير موجود: {$modulePath}"
            );
        }
    }
}
