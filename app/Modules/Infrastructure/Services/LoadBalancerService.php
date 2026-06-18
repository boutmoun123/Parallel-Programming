<?php

namespace App\Modules\Infrastructure\Services;

use App\Models\ServerNode;
use App\Modules\Infrastructure\Data\ServerNodeAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Throwable;

class LoadBalancerService
{
    public function acquireNode(): ?ServerNodeAssignment
    {
        try {
            $node = $this->selectLeastLoadedNode();

            if (! $node) {
                return null;
            }

            try {
                $volatileLoad = $this->incrementVolatileLoad((int) $node->id);
                $currentLoad = (int) $node->current_load + $volatileLoad;
                $strategy = 'least-loaded-redis-counter';
            } catch (Throwable) {
                $currentLoad = (int) $node->current_load;
                $strategy = 'least-loaded-config-fallback';
            }

            return new ServerNodeAssignment(
                $node->id,
                $node->name,
                $node->host,
                $strategy,
                $currentLoad,
            );
        } catch (Throwable) {
            return null;
        }
    }

    public function releaseNode(ServerNodeAssignment $assignment): void
    {
        try {
            $this->decrementVolatileLoad($assignment->nodeId);
        } catch (Throwable) {
            //
        }
    }

    private function selectLeastLoadedNode(): ?ServerNode
    {
        /** @var Collection<int, ServerNode> $nodes */
        $nodes = ServerNode::query()
            ->whereIn('status', [
                ServerNode::STATUS_ACTIVE,
                ServerNode::STATUS_OVERLOADED,
            ])
            ->get();

        return $nodes
            ->sort(function (ServerNode $left, ServerNode $right): int {
                $leftStatusRank = $left->status === ServerNode::STATUS_ACTIVE ? 0 : 1;
                $rightStatusRank = $right->status === ServerNode::STATUS_ACTIVE ? 0 : 1;

                if ($leftStatusRank !== $rightStatusRank) {
                    return $leftStatusRank <=> $rightStatusRank;
                }

                $ratioComparison = $this->effectiveLoadRatio($left) <=> $this->effectiveLoadRatio($right);

                if ($ratioComparison !== 0) {
                    return $ratioComparison;
                }

                $loadComparison = $this->effectiveLoad($left) <=> $this->effectiveLoad($right);

                if ($loadComparison !== 0) {
                    return $loadComparison;
                }

                return random_int(0, 1) === 0 ? -1 : 1;
            })
            ->first();
    }

    private function effectiveLoadRatio(ServerNode $node): float
    {
        $capacity = max(1, (int) $node->max_concurrent_requests);

        return $this->effectiveLoad($node) / $capacity;
    }

    private function effectiveLoad(ServerNode $node): int
    {
        return (int) $node->current_load + $this->volatileLoad((int) $node->id);
    }

    private function incrementVolatileLoad(int $nodeId): int
    {
        $cache = $this->cache();
        $key = $this->loadKey($nodeId);

        $cache->add($key, 0, now()->addSeconds($this->counterTtlSeconds()));

        return (int) $cache->increment($key);
    }

    private function decrementVolatileLoad(int $nodeId): void
    {
        $cache = $this->cache();
        $key = $this->loadKey($nodeId);

        if (! $cache->has($key)) {
            return;
        }

        $load = (int) $cache->decrement($key);

        if ($load <= 0) {
            $cache->forget($key);
        }
    }

    private function volatileLoad(int $nodeId): int
    {
        try {
            return max(0, (int) $this->cache()->get($this->loadKey($nodeId), 0));
        } catch (Throwable) {
            return 0;
        }
    }

    private function loadKey(int $nodeId): string
    {
        return "server-nodes:{$nodeId}:volatile-load";
    }

    private function counterTtlSeconds(): int
    {
        return max(10, (int) config('load_balancer.counter_ttl_seconds', 120));
    }

    private function cache(): \Illuminate\Contracts\Cache\Repository
    {
        return Cache::store(config('load_balancer.store', config('cache.default')));
    }
}
