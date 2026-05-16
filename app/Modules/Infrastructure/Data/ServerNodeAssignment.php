<?php

namespace App\Modules\Infrastructure\Data;

class ServerNodeAssignment
{
    public function __construct(
        public readonly int $nodeId,
        public readonly string $nodeName,
        public readonly string $host,
        public readonly string $strategy,
        public readonly int $currentLoad,
    ) {
    }
}
