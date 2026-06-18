<?php

namespace App\Modules\ServerNodes\Services;

use App\Models\ServerNode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ServerNodeService
{
    private const CACHE_TTL_SECONDS = 30;
    private const LEGACY_SERVER_NODES_CACHE_KEY = 'server-nodes:latest';
    private const LATEST_SERVER_NODES_CACHE_KEY = 'server-nodes:latest:v2';

    /**
     * @return Collection<int, ServerNode>
     */
    public function getLatestServerNodes(): Collection
    {
        $cache = Cache::store(config('cache.default'));
        $cache->forget(self::LEGACY_SERVER_NODES_CACHE_KEY);

        $cachedRows = $cache->get(self::LATEST_SERVER_NODES_CACHE_KEY);

        if (is_array($cachedRows) && $this->isValidCachedRows($cachedRows)) {
            return $this->modelsFromRows($cachedRows);
        }

        if ($cachedRows !== null) {
            $cache->forget(self::LATEST_SERVER_NODES_CACHE_KEY);
        }

        $serverNodes = ServerNode::query()->latest()->get();

        $cache->put(
            self::LATEST_SERVER_NODES_CACHE_KEY,
            $serverNodes->map(fn (ServerNode $serverNode): array => $this->rowFromModel($serverNode))->all(),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
        );

        return $serverNodes;
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
        $cache = Cache::store(config('cache.default'));

        $cache->forget(self::LEGACY_SERVER_NODES_CACHE_KEY);
        $cache->forget(self::LATEST_SERVER_NODES_CACHE_KEY);
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function isValidCachedRows(array $rows): bool
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                return false;
            }

            foreach (['id', 'name', 'host', 'status', 'max_concurrent_requests', 'current_load'] as $key) {
                if (! array_key_exists($key, $row)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<int, ServerNode>
     */
    private function modelsFromRows(array $rows): Collection
    {
        return new Collection(array_map(function (array $row): ServerNode {
            $serverNode = new ServerNode();
            $serverNode->forceFill($row);
            $serverNode->exists = true;

            return $serverNode;
        }, $rows));
    }

    /**
     * @return array<string, mixed>
     */
    private function rowFromModel(ServerNode $serverNode): array
    {
        return [
            'id' => $serverNode->id,
            'name' => $serverNode->name,
            'host' => $serverNode->host,
            'status' => $serverNode->status,
            'max_concurrent_requests' => $serverNode->max_concurrent_requests,
            'current_load' => $serverNode->current_load,
            'created_at' => $serverNode->created_at?->toISOString(),
            'updated_at' => $serverNode->updated_at?->toISOString(),
        ];
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
