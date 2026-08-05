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
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Tenant relationship
            $table->unsignedBigInteger('tenant_id')->nullable();
            
            // Tax rate identification
            $table->string('code')->unique();
            $table->string('name');
            
            // Rate value (percentage, e.g., 15 = 15%)
            $table->unsignedBigInteger('rate');
            
            // Description
            $table->text('description')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            
            // Effective period
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'is_default']);
            $table->index(['code']);
            $table->index(['effective_from', 'effective_to']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
    }
};
