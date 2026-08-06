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
        Schema::create('field_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->string('type')->default('installation'); // installation, repair, survey
            $table->string('status')->default('assigned'); // assigned, en_route, on_site, completed, cancelled

            // Location
            $table->text('address')->nullable();
            $table->decimal('geo_lat', 10, 8)->nullable();
            $table->decimal('geo_lng', 11, 8)->nullable();

            // Timing
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            // Notes
            $table->text('technician_notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('assigned_to');
            $table->index('ticket_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_jobs');
    }
};
