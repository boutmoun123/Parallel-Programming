<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\AssignServerNodeMiddleware;
use App\Http\Middleware\CapacityControlMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
            'capacity' => CapacityControlMiddleware::class,
            'load-balance' => AssignServerNodeMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Database\QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'database is locked')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Database is temporarily busy. Please retry shortly.',
                'data' => null,
                'errors' => [
                    'database' => ['SQLite write lock detected during stress testing.'],
                ],
            ], 503, [
                'Retry-After' => '1',
            ]);
        });
    })->create();
