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
        // Data export requests table
        Schema::create('customer_data_export_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('requested_by_admin')->nullable()->constrained('users');
            
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'expired'])->default('pending');
            $table->json('requested_data_types')->nullable();
            $table->enum('format', ['json', 'csv', 'xlsx'])->default('json');
            
            $table->text('download_url')->nullable();
            $table->dateTime('download_expires_at')->nullable();
            
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['customer_id', 'status']);
            $table->index('uuid');
        });

        // Data deletion requests table
        Schema::create('customer_data_deletion_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('processed_by_admin')->nullable()->constrained('users');
            
            $table->enum('status', ['pending', 'confirmation_required', 'confirmation_sent', 'scheduled', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->enum('scope', ['all', 'specific'])->default('all');
            $table->boolean('confirmation_required')->default(true);
            $table->string('confirmation_token', 64)->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmation_confirmed_at')->nullable();
            
            $table->json('deletion_report')->nullable(); // Stores what was deleted
            
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['customer_id', 'status']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_data_deletion_requests');
        Schema::dropIfExists('customer_data_export_requests');
    }
};
