<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         Product::create([
            'name' => 'Gaming Laptop',
            'description' => 'High performance gaming laptop',
            'price' => 1500.00,
            'stock_quantity' => 2,
            'quantity_counter' => 2,
            'status' => 'active',
            'photos' => [
                'products/laptop-1.jpg',
                'products/laptop-2.jpg'
            ],
        ]);

        Product::create([
            'name' => 'Wireless Mouse',
            'description' => 'Ergonomic wireless mouse',
            'price' => 45.99,
            'stock_quantity' => 100,
            'quantity_counter' => 100,
            'status' => 'active',
            'photos' => [
                'products/mouse-1.jpg'
            ],
        ]);

        Product::create([
            'name' => 'Mechanical Keyboard',
            'description' => 'RGB mechanical keyboard',
            'price' => 120.50,
            'stock_quantity' => 50,
            'quantity_counter' => 50,
            'status' => 'active',
            'photos' => [
                'products/keyboard-1.jpg'
            ],
        ]);
    
    }
}
