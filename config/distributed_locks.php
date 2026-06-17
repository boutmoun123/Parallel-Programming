<?php

return [
    'store' => env('DISTRIBUTED_LOCK_STORE', env('CACHE_STORE', 'redis')),
    'default_seconds' => env('DISTRIBUTED_LOCK_SECONDS', 30),
    'default_wait_seconds' => env('DISTRIBUTED_LOCK_WAIT_SECONDS', 10),
];
