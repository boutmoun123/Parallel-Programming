<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_sales_report_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('daily_sales_report_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->integer('total_quantity_sold')->default(0);
            $table->decimal('total_revenue', 10, 2)->default(0);
            $table->unsignedBigInteger('inventory_movements')->nullable();
            $table->integer('product_rank')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_sales_report_items');
    }
};
