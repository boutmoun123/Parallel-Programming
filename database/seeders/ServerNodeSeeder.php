<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ServerNodeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('server_nodes')->updateOrInsert(
            ['name' => 'server-1'],
            [
                'host' => '127.0.0.1:8000',
                'status' => 'active',
                'max_concurrent_requests' => 100,
                'current_load' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('server_nodes')->updateOrInsert(
            ['name' => 'server-2'],
            [
                'host' => '127.0.0.1:8001',
                'status' => 'active',
                'max_concurrent_requests' => 100,
                'current_load' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('server_nodes')->updateOrInsert(
            ['name' => 'server-3'],
            [
                'host' => '127.0.0.1:8002',
                'status' => 'active',
                'max_concurrent_requests' => 100,
                'current_load' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
