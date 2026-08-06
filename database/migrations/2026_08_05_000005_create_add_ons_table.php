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
        Schema::create('add_ons', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();

            // Add-on type
            $table->enum('type', ['static_ip', 'extra_device_slot', 'ott_iptv', 'voice', 'other'])->default('other');

            // Pricing
            $table->unsignedBigInteger('price')->default(0);
            $table->enum('billing_cycle', ['one_time', 'monthly', 'quarterly', 'annual'])->default('monthly');

            // Configuration
            $table->json('configuration')->nullable();

            // Status and display
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'is_active']);
            $table->index('code');
            $table->index('uuid');
        });

        // Create the pivot table for subscription add-ons
        Schema::create('subscription_add_ons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->foreignId('add_on_id')->constrained('add_ons')->cascadeOnDelete();
            $table->unsignedBigInteger('custom_price')->nullable()->comment('Override price for this subscription');
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['subscription_id', 'add_on_id']);
            $table->index(['subscription_id', 'add_on_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_add_ons');
        Schema::dropIfExists('add_ons');
    }
};
