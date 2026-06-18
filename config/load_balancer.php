<?php

return [
    'strategy' => env('LOAD_BALANCER_STRATEGY', 'least-loaded'),
    'store' => env('LOAD_BALANCER_STORE', env('CACHE_STORE', 'redis')),
    'counter_ttl_seconds' => env('LOAD_BALANCER_COUNTER_TTL_SECONDS', 120),
];
