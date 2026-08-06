<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('device_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_device_id')->constrained('network_devices')->cascadeOnDelete();

            $table->string('status', 20)->default('up'); // up, down, degraded
            $table->integer('uptime_seconds')->nullable();
            $table->decimal('cpu_usage_percent', 5, 2)->nullable();
            $table->decimal('memory_usage_percent', 5, 2)->nullable();
            $table->integer('ping_response_ms')->nullable();

            $table->jsonb('interface_stats')->nullable(); // For MikroTik interface traffic
            $table->jsonb('additional_data')->nullable(); // For OLT temperature, fans, etc.

            $table->timestamp('created_at')->useCurrent();

            // Note: device_metrics is a time-series table, so we don't need updated_at

            $table->index(['network_device_id', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_metrics');
    }
};
