<?php

use App\Enums\ProcurementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('procurements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('po_number', 100)->unique();
            $table->foreignId('supplier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            // Procurement details
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ProcurementStatus::values())->default(ProcurementStatus::DRAFT->value);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            // Dates
            $table->date('order_date')->nullable();
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            $table->date('approved_at')->nullable();

            // Financial
            $table->unsignedBigInteger('subtotal')->nullable()->default(0)->comment('In poysha');
            $table->unsignedBigInteger('tax_amount')->nullable()->default(0)->comment('In poysha');
            $table->unsignedBigInteger('shipping_cost')->nullable()->default(0)->comment('In poysha');
            $table->unsignedBigInteger('total_amount')->nullable()->default(0)->comment('In poysha');
            $table->string('currency', 3)->default('BDT');

            // Tracking
            $table->string('tracking_number', 100)->nullable();
            $table->string('shipping_method', 50)->nullable();
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'po_number']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('supplier_id');
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurements');
    }
};
