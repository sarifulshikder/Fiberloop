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
        Schema::create('subscription_pricing_overrides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            // Override pricing
            $table->unsignedBigInteger('override_price')->comment('Overridden price in poysha (BDT × 100)');
            $table->unsignedBigInteger('override_installation_fee')->nullable()->default(0);
            $table->unsignedBigInteger('override_security_deposit')->nullable()->default(0);

            // Reason for override
            $table->text('reason')->nullable();

            // Status and dates
            $table->boolean('is_active')->default(true);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'subscription_id']);
            $table->index(['subscription_id', 'is_active']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_pricing_overrides');
    }
};
