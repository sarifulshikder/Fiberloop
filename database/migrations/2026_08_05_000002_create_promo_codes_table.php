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
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Discount configuration
            $table->enum('discount_type', ['percentage', 'fixed_amount', 'fixed_price'])->default('percentage');
            $table->unsignedBigInteger('discount_value')->default(0);

            // Applicability
            $table->enum('applies_to', ['all_packages', 'specific_packages', 'minimum_amount'])->default('all_packages');

            // Time constraints
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();

            // Usage limits
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->unsignedInteger('max_uses_per_customer')->nullable();

            // Status
            $table->boolean('is_active')->default(true);

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'code']);
            $table->index(['tenant_id', 'is_active']);
            $table->index('code');
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};