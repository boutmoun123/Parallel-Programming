<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->index('status', 'orders_status_index');
            $table->index('user_id', 'orders_user_id_index');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->index('product_id', 'inventory_movements_product_id_index');
            $table->index('type', 'inventory_movements_type_index');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->index('quantity_counter', 'products_quantity_counter_index');
        });

        Schema::table('request_logs', function (Blueprint $table): void {
            $table->index('server_node_id', 'request_logs_server_node_id_index');
        });

        Schema::table('daily_sales_reports', function (Blueprint $table): void {
            $table->index('report_date', 'daily_sales_reports_report_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('daily_sales_reports', function (Blueprint $table): void {
            $table->dropIndex('daily_sales_reports_report_date_index');
        });

        Schema::table('request_logs', function (Blueprint $table): void {
            $table->dropIndex('request_logs_server_node_id_index');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_quantity_counter_index');
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropIndex('inventory_movements_product_id_index');
            $table->dropIndex('inventory_movements_type_index');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_status_index');
            $table->dropIndex('orders_user_id_index');
        });
    }
};
