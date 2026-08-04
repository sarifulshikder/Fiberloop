<?php

use App\Enums\SubscriptionStatus;
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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('package_id')->constrained('packages');
            $table->unsignedBigInteger('reseller_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            // Subscription details
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_billing_date')->nullable();
            $table->enum('status', SubscriptionStatus::values())->default(SubscriptionStatus::ACTIVE->value);

            // Pricing at time of subscription (snapshotted)
            $table->unsignedBigInteger('monthly_price'); // 1 = 1 taka
            $table->unsignedBigInteger('billing_cycle_discount')->default(0);
            $table->unsignedBigInteger('final_price');

            // Proration
            $table->boolean('is_prorated')->default(false);
            $table->unsignedBigInteger('proration_amount')->default(0);
            $table->text('proration_notes')->nullable();

            // Connection details
            $table->string('assigned_ip')->nullable()->unique();
            $table->string('assigned_mac')->nullable();
            $table->string('assigned_port')->nullable();
            $table->string('assigned_vlan')->nullable();

            // Network device links (optional, for tracking)
            $table->unsignedBigInteger('network_device_id')->nullable();
            $table->unsignedBigInteger('olt_id')->nullable();
            $table->unsignedBigInteger('onu_id')->nullable();

            // Status tracking
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('expired_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('suspended_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->text('suspension_reason')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index('next_billing_date');
            $table->index('package_id');
            $table->index('reseller_id');
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
