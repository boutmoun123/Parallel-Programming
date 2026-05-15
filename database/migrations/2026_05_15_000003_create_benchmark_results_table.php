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
        Schema::create('benchmark_results', function (Blueprint $table) {
            $table->id();
            $table->string('operation_name');
            $table->string('scenario')->nullable();
            $table->integer('concurrent_users')->default(1);
            $table->integer('total_requests')->default(0);
            $table->integer('successful_requests')->default(0);
            $table->integer('failed_requests')->default(0);
            $table->integer('average_response_time_ms')->nullable();
            $table->integer('max_response_time_ms')->nullable();
            $table->decimal('throughput_per_second', 10, 2)->nullable();
            $table->text('bottleneck_note')->nullable();
            $table->string('optimization_applied')->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('benchmark_results');
    }
};
