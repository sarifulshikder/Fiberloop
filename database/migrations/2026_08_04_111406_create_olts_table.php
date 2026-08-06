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
        Schema::create('olts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('network_device_id');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            // OLT identification
            $table->string('name');
            $table->string('chassis_id')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('hardware_version')->nullable();
            $table->string('uptime')->nullable();

            // Capacity
            $table->integer('total_pon_ports')->default(0);
            $table->integer('used_pon_ports')->default(0);
            $table->integer('max_onus_per_pon')->default(64);

            // Location
            $table->string('rack')->nullable();
            $table->string('slot')->nullable();
            $table->text('location_notes')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_sync_at')->nullable();

            // Metadata
            $table->json('configuration')->nullable();
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'network_device_id']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('olts');
    }
};
