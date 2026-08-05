<?php

use App\Enums\PackageChangeRequestStatus;
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
        Schema::create('package_change_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('subscription_id')->constrained('subscriptions');
            $table->foreignId('current_package_id')->constrained('packages');
            $table->foreignId('requested_package_id')->constrained('packages');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('rejected_by')->nullable()->constrained('users');

            // Request details
            $table->enum('type', ['upgrade', 'downgrade', 'change'])->default('change');
            $table->enum('status', PackageChangeRequestStatus::values())->default(PackageChangeRequestStatus::PENDING->value);
            $table->text('reason')->nullable();

            // Proration
            $table->date('effective_date')->nullable();
            $table->bigInteger('proration_amount')->nullable()->comment('Amount in smallest unit (poysha)');
            $table->boolean('is_prorated')->default(false);

            // Approval
            $table->text('approval_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('rejected_at')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'status']);
            $table->index('subscription_id');
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_change_requests');
    }
};
