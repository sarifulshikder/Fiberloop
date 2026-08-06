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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('split_from_payment_id')
                ->nullable()
                ->constrained('payments')
                ->comment('ID of the parent payment this was split from (for multi-invoice allocations)');

            $table->boolean('is_partial')
                ->default(false)
                ->comment('Whether this payment is a partial payment or allocation from a multi-invoice payment');

            $table->boolean('is_wallet_topup')
                ->default(false)
                ->comment('Whether this payment was used for wallet top-up');

            $table->boolean('applied_to_invoice')
                ->default(true)
                ->comment('Whether this payment has been applied to an invoice');

            // Indexes for performance
            $table->index('split_from_payment_id');
            $table->index('is_partial');
            $table->index('is_wallet_topup');
            $table->index('applied_to_invoice');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['split_from_payment_id']);
            $table->dropColumn('split_from_payment_id');
            $table->dropColumn('is_partial');
            $table->dropColumn('is_wallet_topup');
            $table->dropColumn('applied_to_invoice');
        });
    }
};
