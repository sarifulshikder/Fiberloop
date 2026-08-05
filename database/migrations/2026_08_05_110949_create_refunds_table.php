<?php

use App\Enums\RefundStatus;
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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Relationships
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('payment_id')->constrained('payments');
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->foreignId('processed_by')->nullable()->constrained('users');
            
            // Refund details
            $table->string('refund_number')->unique();
            $table->text('reason')->nullable();
            $table->date('request_date');
            $table->date('processed_date')->nullable();
            
            // Amounts (1 = 1 poysha/BDT x 100)
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee_amount')->default(0)->comment('Refund processing fee');
            $table->unsignedBigInteger('net_amount')->default(0);
            
            // Status
            $table->enum('status', RefundStatus::values())->default(RefundStatus::PENDING->value);
            
            // Gateway references
            $table->string('gateway_reference')->nullable()->comment('Refund transaction ID from gateway');
            $table->string('gateway_response')->nullable()->comment('Raw response from gateway');
            
            // Metadata
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            
            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['tenant_id', 'payment_id']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['refund_number']);
            $table->index(['request_date']);
            $table->index(['processed_date']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
