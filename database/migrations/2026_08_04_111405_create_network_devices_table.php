<?php

use App\Enums\DeviceVendor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('network_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            // Device identification
            $table->string('name');
            $table->enum('vendor', DeviceVendor::values());
            $table->string('model');
            $table->string('serial_number')->nullable()->unique();

            // Network connectivity
            $table->string('ip_address')->unique();
            $table->string('hostname')->nullable();
            $table->integer('port')->default(22);

            // Authentication
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('snmp_community')->nullable();
            $table->string('snmp_version')->nullable()->default('v2c');

            // Location
            $table->string('location')->nullable();
            $table->string('latitude', 50)->nullable();
            $table->string('longitude', 50)->nullable();
            $table->text('address')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_checked_at')->nullable();
            $table->boolean('is_reachable')->default(false);

            // Metadata
            $table->json('capabilities')->nullable()->comment('Supported features');
            $table->json('configuration')->nullable();
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'is_active']);
            $table->index('ip_address');
            $table->index('vendor');
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('network_devices');
    }
};
