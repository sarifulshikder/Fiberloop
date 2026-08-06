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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('invoice_id')->constrained('invoices');

            // Line item details
            $table->string('description');
            $table->string('item_type')->default('service'); // service, fee, tax, discount, etc.

            // Pricing (1 = 1 taka)
            $table->unsignedBigInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->default(0);
            $table->unsignedBigInteger('amount')->default(0);

            // Period (optional, for service charges)
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            // Metadata
            $table->json('metadata')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'invoice_id']);
            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
