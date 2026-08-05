<?php

use App\Enums\WalletTransactionType;
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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // Tenant and customer
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('customer_id')->constrained('customers');
            
            // Related models (optional)
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->unsignedBigInteger('payment_id')->nullable();
            $table->unsignedBigInteger('invoice_id')->nullable();
            
            // Polymorphic reference
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            
            // Transaction type and amounts
            $table->enum('type', WalletTransactionType::values());
            $table->unsignedBigInteger('amount')->comment('Amount in poysha (BDT x 100)');
            $table->unsignedBigInteger('balance_before')->comment('Wallet balance before transaction in poysha');
            $table->unsignedBigInteger('balance_after')->comment('Wallet balance after transaction in poysha');
            
            // Description and metadata
            $table->text('description');
            $table->string('gateway_reference')->nullable();
            $table->json('metadata')->nullable();
            
            // Created by
            $table->foreignId('created_by')->nullable()->constrained('users');
            
            // Timestamps
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'type']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['subscription_id']);
            $table->index(['payment_id']);
            $table->index(['invoice_id']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
