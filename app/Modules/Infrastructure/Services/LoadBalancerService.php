<?php

namespace App\Modules\Infrastructure\Services;

use App\Models\ServerNode;
use App\Modules\Infrastructure\Data\ServerNodeAssignment;
use Illuminate\Support\Facades\DB;

class LoadBalancerService
{
    public function acquireNode(): ?ServerNodeAssignment
    {
        return DB::transaction(function (): ?ServerNodeAssignment {
            // Lock a single candidate row so concurrent requests cannot pick the same node blindly.
            $node = ServerNode::query()
                ->whereIn('status', [
                    ServerNode::STATUS_ACTIVE,
                    ServerNode::STATUS_OVERLOADED,
                ])
                ->orderByRaw(
                    "CASE WHEN status = ? THEN 0 ELSE 1 END",
                    [ServerNode::STATUS_ACTIVE],
                )
                ->orderByRaw('(current_load * 1.0) / max_concurrent_requests')
                ->orderBy('current_load')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if (! $node) {
                return null;
            }

            $node->current_load++;
            $this->applyNodeStatus($node);
            $node->save();

            return new ServerNodeAssignment(
                $node->id,
                $node->name,
                $node->host,
                config('load_balancer.strategy', 'least-loaded'),
                $node->current_load,
            );
        });
    }

    public function releaseNode(ServerNodeAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            $node = ServerNode::query()
                ->whereKey($assignment->nodeId)
                ->lockForUpdate()
                ->first();

            if (! $node) {
                return;
            }

            $node->current_load = max(0, $node->current_load - 1);
            $this->applyNodeStatus($node);
            $node->save();
        });
    }

    private function applyNodeStatus(ServerNode $node): void
    {
        if ($node->status === ServerNode::STATUS_INACTIVE) {
            return;
        }

        $node->status = $node->current_load > $node->max_concurrent_requests
            ? ServerNode::STATUS_OVERLOADED
            : ServerNode::STATUS_ACTIVE;
    }
}
