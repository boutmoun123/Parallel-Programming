<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RequestLog;
use App\Models\ServerNode;
use App\Models\User;
use App\Modules\Infrastructure\Data\ServerNodeAssignment;
use App\Modules\Infrastructure\Services\LoadBalancerService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoadDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_request_uses_least_loaded_active_node_and_creates_request_log(): void
    {
        $user = User::create([
            'name' => 'Load User',
            'phone' => '966500000701',
            'password' => 'User12345',
            'role' => 'user',
        ]);

        Sanctum::actingAs($user);

        $nodeA = ServerNode::create([
            'name' => 'Node A',
            'host' => '10.0.0.1',
            'status' => ServerNode::STATUS_ACTIVE,
            'max_concurrent_requests' => 20,
            'current_load' => 3,
        ]);

        $nodeB = ServerNode::create([
            'name' => 'Node B',
            'host' => '10.0.0.2',
            'status' => ServerNode::STATUS_ACTIVE,
            'max_concurrent_requests' => 20,
            'current_load' => 1,
        ]);

        ServerNode::create([
            'name' => 'Node C',
            'host' => '10.0.0.3',
            'status' => ServerNode::STATUS_INACTIVE,
            'max_concurrent_requests' => 20,
            'current_load' => 0,
        ]);

        $response = $this->getJson('/api/carts');

        $response->assertOk()
            ->assertHeader('X-Load-Balancer-Strategy', 'least-loaded')
            ->assertHeader('X-Server-Node', 'Node B')
            ->assertHeader('X-Server-Node-Id', (string) $nodeB->id);

        $log = RequestLog::query()->latest()->firstOrFail();

        $this->assertSame($nodeB->id, $log->server_node_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('CartController::index', $log->operation_name);
        $this->assertSame('/api/carts', $log->endpoint);
        $this->assertSame('GET', $log->method);
        $this->assertSame(200, $log->status_code);

        $nodeA->refresh();
        $nodeB->refresh();

        $this->assertSame(3, $nodeA->current_load);
        $this->assertSame(1, $nodeB->current_load);
    }

    public function test_request_continues_without_server_nodes_and_is_logged_as_unassigned(): void
    {
        Product::create([
            'name' => 'Public Product',
            'description' => 'Visible without authentication',
            'price' => 12.5,
            'stock_quantity' => 4,
            'quantity_counter' => 4,
            'status' => 'active',
            'photos' => [],
        ]);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertHeader('X-Load-Balancer-Strategy', 'least-loaded')
            ->assertHeader('X-Server-Node', 'unassigned');

        $log = RequestLog::query()->latest()->firstOrFail();

        $this->assertNull($log->server_node_id);
        $this->assertNull($log->user_id);
        $this->assertSame('ProductController::index', $log->operation_name);
        $this->assertSame('/api/products', $log->endpoint);
        $this->assertSame('GET', $log->method);
        $this->assertSame(200, $log->status_code);
    }

    public function test_request_does_not_return_500_when_node_assignment_hits_sqlite_lock(): void
    {
        $this->app->instance(LoadBalancerService::class, new class extends LoadBalancerService
        {
            public function acquireNode(): ?ServerNodeAssignment
            {
                throw new QueryException(
                    'update "server_nodes" set "current_load" = ?',
                    [],
                    new \Exception('SQLSTATE[HY000]: General error: 5 database is locked')
                );
            }
        });

        Product::create([
            'name' => 'Fallback Product',
            'description' => 'Visible even if node assignment fails',
            'price' => 12.5,
            'stock_quantity' => 4,
            'quantity_counter' => 4,
            'status' => 'active',
            'photos' => [],
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertHeader('X-Server-Node', 'unassigned');
    }

    public function test_load_balancer_uses_cache_counter_without_updating_server_node_row(): void
    {
        config(['load_balancer.store' => 'array']);

        $node = ServerNode::create([
            'name' => 'Redis Counter Node',
            'host' => '10.0.0.10',
            'status' => ServerNode::STATUS_ACTIVE,
            'max_concurrent_requests' => 20,
            'current_load' => 0,
        ]);

        $service = app(LoadBalancerService::class);
        $assignment = $service->acquireNode();

        $this->assertNotNull($assignment);
        $this->assertSame($node->id, $assignment->nodeId);
        $this->assertSame(1, $assignment->currentLoad);
        $this->assertSame(1, Cache::store('array')->get("server-nodes:{$node->id}:volatile-load"));

        $node->refresh();
        $this->assertSame(0, $node->current_load);

        $service->releaseNode($assignment);
        $this->assertNull(Cache::store('array')->get("server-nodes:{$node->id}:volatile-load"));
    }

    public function test_server_nodes_endpoint_recovers_from_invalid_cached_node_data(): void
    {
        config(['cache.default' => 'array']);

        $user = User::create([
            'name' => 'Server Node Cache User',
            'phone' => '966500000702',
            'password' => 'User12345',
            'role' => 'user',
        ]);

        Sanctum::actingAs($user);

        $node = ServerNode::create([
            'name' => 'Safe Config Node',
            'host' => '10.0.0.20',
            'status' => ServerNode::STATUS_ACTIVE,
            'max_concurrent_requests' => 20,
            'current_load' => 0,
        ]);

        Cache::store('array')->put('server-nodes:latest:v2', 'corrupt-cache-value', 60);

        $this->getJson('/api/server-nodes')
            ->assertOk()
            ->assertJsonPath('data.0.id', $node->id)
            ->assertJsonPath('data.0.name', 'Safe Config Node');

        $this->assertIsArray(Cache::store('array')->get('server-nodes:latest:v2'));
    }

    public function test_server_nodes_endpoint_forgets_legacy_eloquent_collection_cache_key(): void
    {
        config(['cache.default' => 'array']);

        $user = User::create([
            'name' => 'Legacy Cache User',
            'phone' => '966500000703',
            'password' => 'User12345',
            'role' => 'user',
        ]);

        Sanctum::actingAs($user);

        ServerNode::create([
            'name' => 'Legacy Safe Node',
            'host' => '10.0.0.21',
            'status' => ServerNode::STATUS_ACTIVE,
            'max_concurrent_requests' => 20,
            'current_load' => 0,
        ]);

        Cache::store('array')->put('server-nodes:latest', ['legacy-or-incomplete-class-like-value'], 60);

        $this->getJson('/api/server-nodes')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Legacy Safe Node');

        $this->assertNull(Cache::store('array')->get('server-nodes:latest'));
    }
}
