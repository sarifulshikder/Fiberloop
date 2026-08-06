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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();

            // What went down?
            $table->foreignId('network_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('olt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('area_zone')->nullable(); // If it's a regional outage

            $table->string('title');
            $table->text('description')->nullable();

            $table->string('status')->default('open'); // open, investigating, resolved
            $table->string('severity')->default('medium'); // low, medium, high, critical

            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
