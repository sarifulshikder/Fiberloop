<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->foreignId('customer_id')->constrained('customers');
            $table->unsignedBigInteger('reseller_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('collected_by')->nullable()->constrained('users')->comment('Field agent who collected cash');

            // Amounts (1 = 1 taka)
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee_amount')->default(0)->comment('Gateway or processing fee');
            $table->unsignedBigInteger('net_amount')->default(0);

            // Payment method and status
            $table->enum('method', PaymentMethod::values());
            $table->enum('status', PaymentStatus::values())->default(PaymentStatus::PENDING->value);

            // Gateway references
            $table->string('gateway_reference')->nullable()->comment('Transaction ID from payment gateway');
            $table->string('gateway_response')->nullable()->comment('Raw response from gateway');

            // Metadata
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('failure_reason')->nullable();

            // File storage (for receipts, etc.)
            $table->string('receipt_path')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['invoice_id', 'status']);
            $table->index(['tenant_id', 'status']);
            $table->index(['gateway_reference', 'method']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
