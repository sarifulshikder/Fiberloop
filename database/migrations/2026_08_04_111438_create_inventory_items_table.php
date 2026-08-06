<?php

use App\Enums\InventoryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions');
            $table->unsignedBigInteger('reseller_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            // Item identification
            $table->string('name');
            $table->enum('item_type', ['router', 'onu', 'cable', 'switch', 'olt', 'sfp', 'accessory', 'other']);
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();

            // Serial and tracking
            $table->string('serial_number')->nullable()->unique();
            $table->string('imei')->nullable()->unique();
            $table->string('mac_address')->nullable()->unique();
            $table->string('barcode')->nullable()->unique();
            $table->string('asset_tag')->nullable()->unique();

            // Status
            $table->enum('status', InventoryStatus::values())->default(InventoryStatus::IN_STOCK->value);

            // Location
            $table->string('warehouse')->nullable();
            $table->string('bin_location')->nullable();
            $table->string('assigned_location')->nullable();

            // Financial
            $table->unsignedBigInteger('purchase_price')->nullable()->comment('1 = 1 taka');
            $table->unsignedBigInteger('selling_price')->nullable()->comment('1 = 1 taka');
            $table->date('purchase_date')->nullable();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('invoices');
            $table->foreignId('supplier_id')->nullable()->comment('For future supplier management');

            // Warranty
            $table->date('warranty_start')->nullable();
            $table->date('warranty_end')->nullable();
            $table->integer('warranty_months')->nullable();

            // Assignment details
            $table->dateTime('assigned_at')->nullable();
            $table->dateTime('returned_at')->nullable();
            $table->text('assignment_notes')->nullable();

            // Condition
            $table->string('condition')->nullable()->default('new');
            $table->text('condition_notes')->nullable();

            // Metadata
            $table->json('specifications')->nullable();
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'item_type']);
            $table->index(['tenant_id', 'status']);
            $table->index('customer_id');
            $table->index('subscription_id');
            $table->index('serial_number');
            $table->index('mac_address');
            $table->index('barcode');
            $table->index('asset_tag');
            $table->index(['warranty_end', 'status']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
