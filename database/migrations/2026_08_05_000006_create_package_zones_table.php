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
        Schema::create('package_zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->string('zone')->nullable();
            $table->string('area')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            // Availability settings
            $table->boolean('is_available')->default(true);

            // Optional custom pricing for this zone
            $table->unsignedBigInteger('custom_price')->nullable();

            // Capacity constraints
            $table->unsignedInteger('max_connections')->nullable();
            $table->unsignedInteger('current_connections')->default(0);

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'package_id']);
            $table->index(['package_id', 'zone']);
            $table->index(['package_id', 'area']);
            $table->unique(['package_id', 'zone', 'area']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_zones');
    }
};