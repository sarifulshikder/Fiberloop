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
        Schema::table('invoices', function (Blueprint $table) {
            // Add tax rate for audit purposes
            $table->unsignedBigInteger('tax_rate')->default(0)->after('tax_amount');

            // Add proration fields
            $table->boolean('is_prorated')->default(false)->after('notes');
            $table->unsignedBigInteger('proration_amount')->default(0)->after('is_prorated');

            // Add billing type (postpaid, prepaid)
            $table->string('billing_type')->nullable()->after('proration_amount');

            // Add indexes for new fields
            $table->index(['tenant_id', 'is_prorated']);
            $table->index('billing_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['tax_rate', 'is_prorated', 'proration_amount', 'billing_type']);
        });
    }
};
