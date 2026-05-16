<?php

return [
    'store' => env('CAPACITY_STORE', env('CACHE_STORE', 'database')),
    'default_limit' => env('CAPACITY_DEFAULT_LIMIT', 10),
    'retry_after_seconds' => env('CAPACITY_RETRY_AFTER_SECONDS', 2),
    'reservation_ttl_seconds' => env('CAPACITY_RESERVATION_TTL_SECONDS', 120),
    'lock_seconds' => env('CAPACITY_LOCK_SECONDS', 5),
    'wait_seconds' => env('CAPACITY_WAIT_SECONDS', 2),
    'groups' => [
        'critical-operations' => [
            'limit' => env('CAPACITY_CRITICAL_LIMIT', 12),
            'retry_after_seconds' => env('CAPACITY_CRITICAL_RETRY_AFTER_SECONDS', 2),
        ],
        'checkout' => [
            'limit' => env('CAPACITY_CHECKOUT_LIMIT', 4),
            'retry_after_seconds' => env('CAPACITY_CHECKOUT_RETRY_AFTER_SECONDS', 2),
        ],
    ],
];
