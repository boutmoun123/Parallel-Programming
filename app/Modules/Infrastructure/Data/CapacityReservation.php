<?php

namespace App\Modules\Infrastructure\Data;

class CapacityReservation
{
    public function __construct(
        public readonly string $group,
        public readonly string $token,
        public readonly int $limit,
        public readonly int $activeCount,
        public readonly int $retryAfterSeconds,
    ) {
    }
}
