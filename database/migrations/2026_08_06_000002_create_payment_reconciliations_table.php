<?php

use App\Enums\PaymentMethod;
use App\Enums\ReconciliationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Tenant and foreign keys
            $table->unsignedBigInteger('tenant_id');
            $table->foreignId('payment_id')->nullable()->constrained('payments');

            // Gateway information
            $table->enum('gateway', PaymentMethod::values());
            $table->string('gateway_reference')->nullable()->comment('Transaction reference from gateway');

            // Amounts (in poysha)
            $table->unsignedBigInteger('recorded_amount')->default(0)->comment('Amount recorded in our system');
            $table->unsignedBigInteger('settlement_amount')->default(0)->comment('Amount from gateway settlement');

            // Dates
            $table->dateTime('settlement_date')->nullable()->comment('Settlement date from gateway');

            // Status
            $table->enum('status', ReconciliationStatus::values())->default(ReconciliationStatus::PENDING->value);

            // Metadata
            $table->text('notes')->nullable();
            $table->json('settlement_data')->nullable()->comment('Raw settlement data from gateway');

            // Resolution tracking
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->dateTime('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'payment_id']);
            $table->index(['tenant_id', 'gateway']);
            $table->index(['tenant_id', 'status']);
            $table->index(['gateway_reference', 'gateway']);
            $table->index(['settlement_date']);
            $table->index(['resolved_at']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_reconciliations');
    }
};
