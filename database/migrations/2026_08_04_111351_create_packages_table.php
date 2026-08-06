<?php

use App\Enums\BillingType;
use App\Enums\PackageBillingCycle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            // Package details
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('code')->unique();

            // Speed limits (in Mbps)
            $table->unsignedInteger('download_speed');
            $table->unsignedInteger('upload_speed');

            // FUP (Fair Usage Policy)
            $table->unsignedBigInteger('fup_threshold')->nullable()->comment('In MB');
            $table->unsignedInteger('fup_throttled_download')->nullable()->comment('Speed after FUP in Mbps');
            $table->unsignedInteger('fup_throttled_upload')->nullable()->comment('Speed after FUP in Mbps');

            // Pricing
            $table->unsignedBigInteger('price'); // 1 = 1 taka (BDT)
            $table->enum('billing_cycle', PackageBillingCycle::values())->default(PackageBillingCycle::MONTHLY->value);
            $table->enum('billing_type', BillingType::values())->default(BillingType::POSTPAID->value);
            $table->unsignedBigInteger('installation_fee')->default(0);
            $table->unsignedBigInteger('security_deposit')->default(0);

            // Tax and fees
            $table->unsignedBigInteger('tax_rate')->default(0)->comment('Percentage as integer (e.g., 15 = 15%)');

            // Status and metadata
            $table->boolean('is_active')->default(true);
            $table->boolean('is_popular')->default(false);
            $table->integer('sort_order')->default(0);
            $table->json('features')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
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
        Schema::dropIfExists('packages');
    }
};
