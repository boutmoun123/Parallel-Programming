<?php

namespace App\Modules\ServerNodes\Services;

use App\Models\ServerNode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ServerNodeService
{
    private const CACHE_TTL_SECONDS = 30;
    private const LATEST_SERVER_NODES_CACHE_KEY = 'server-nodes:latest';

    /**
     * @return Collection<int, ServerNode>
     */
    public function getLatestServerNodes(): Collection
    {
        return Cache::store(config('cache.default'))->remember(
            self::LATEST_SERVER_NODES_CACHE_KEY,
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            fn (): Collection => ServerNode::query()->latest()->get(),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createServerNode(array $data): ServerNode
    {
        $data['status'] ??= ServerNode::STATUS_ACTIVE;
        $data['current_load'] ??= 0;

        $serverNode = ServerNode::create($this->applyLoadStatus($data));
        $this->forgetServerNodesCache();

        return $serverNode;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateServerNode(ServerNode $serverNode, array $data): ServerNode
    {
        $data = array_merge([
            'max_concurrent_requests' => $serverNode->max_concurrent_requests,
            'current_load' => $serverNode->current_load,
            'status' => $serverNode->status,
        ], $data);

        $serverNode->fill($this->applyLoadStatus($data));
        $serverNode->save();
        $this->forgetServerNodesCache();

        return $serverNode->fresh();
    }

    public static function forgetServerNodesCache(): void
    {
        Cache::store(config('cache.default'))->forget(self::LATEST_SERVER_NODES_CACHE_KEY);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyLoadStatus(array $data): array
    {
        if ((int) $data['current_load'] > (int) $data['max_concurrent_requests']) {
            $data['status'] = ServerNode::STATUS_OVERLOADED;

            return $data;
        }

        if (($data['status'] ?? null) !== ServerNode::STATUS_INACTIVE) {
            $data['status'] = ServerNode::STATUS_ACTIVE;
        }

        return $data;
    }
}
