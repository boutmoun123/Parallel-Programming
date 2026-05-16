<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\RequestLog;
use App\Models\ServerNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
