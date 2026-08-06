<?php

use App\Enums\ProcurementItemStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('procurement_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('procurement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();

            // Item details (can be set even if inventory_item_id is null, for items not yet created)
            $table->string('item_type')->nullable();
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('mac_address')->nullable();

            // Procurement details
            $table->integer('quantity')->default(1);
            $table->unsignedBigInteger('unit_price')->comment('Price per unit in poysha');
            $table->unsignedBigInteger('total_price')->comment('Total price in poysha');
            $table->enum('status', ProcurementItemStatus::values())->default(ProcurementItemStatus::PENDING->value);
            $table->integer('received_quantity')->default(0);

            // Timestamps
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['procurement_id', 'inventory_item_id']);
            $table->index(['procurement_id', 'status']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procurement_items');
    }
};
