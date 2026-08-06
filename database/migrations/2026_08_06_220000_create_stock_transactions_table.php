<?php

use App\Enums\StockTransactionReason;
use App\Enums\StockTransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('field_job_id')->nullable()->constrained('field_jobs')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            // Transaction details
            $table->enum('transaction_type', StockTransactionType::values());
            $table->enum('reason', StockTransactionReason::values())->nullable();
            $table->string('reference_number', 100)->nullable(); // PO number, ticket number, etc.
            $table->text('notes')->nullable();

            // Previous state
            $table->enum('previous_status', \App\Enums\InventoryStatus::values())->nullable();
            $table->string('previous_location', 255)->nullable();
            $table->foreignId('previous_holder_id')->nullable()->constrained('users')->nullOnDelete();

            // New state
            $table->enum('new_status', \App\Enums\InventoryStatus::values())->nullable();
            $table->string('new_location', 255)->nullable();
            $table->foreignId('new_holder_id')->nullable()->constrained('users')->nullOnDelete();

            // Quantities (for batch items)
            $table->integer('quantity')->default(1);
            $table->unsignedBigInteger('unit_cost')->nullable()->comment('Cost per unit in poysha');
            $table->unsignedBigInteger('total_cost')->nullable()->comment('Total cost in poysha');

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'inventory_item_id']);
            $table->index(['tenant_id', 'transaction_type']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('user_id');
            $table->index('customer_id');
            $table->index('field_job_id');
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
