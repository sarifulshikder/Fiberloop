<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('reseller_commission_ledger', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('reseller_id')->constrained('resellers');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices');
            $table->foreignId('payment_id')->nullable()->constrained('payments');
            $table->foreignId('created_by')->nullable()->constrained('users');

            // Type of ledger entry
            $table->enum('type', ['earned', 'withdrawn', 'adjusted', 'reversed']);

            // Amount in poysha (BDT × 100) — never float
            $table->bigInteger('amount')->comment('Poysha. Positive = credit, negative = debit');
            $table->bigInteger('balance_before')->comment('Wallet balance before this entry, in poysha');
            $table->bigInteger('balance_after')->comment('Wallet balance after this entry, in poysha');

            $table->text('description')->nullable();

            // Immutable — no soft deletes, no updated_at
            $table->timestamp('created_at')->useCurrent();

            // Indexes
            $table->index(['tenant_id', 'reseller_id']);
            $table->index(['reseller_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_commission_ledger');
    }
};
