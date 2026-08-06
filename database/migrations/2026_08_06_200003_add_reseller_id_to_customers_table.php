<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add reseller_id FK to customers table (Phase 9).
 * The column exists in some migrations but wasn't applied to all installs.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'reseller_id')) {
                $table->unsignedBigInteger('reseller_id')->nullable()->after('lead_id');
                $table->index('reseller_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'reseller_id')) {
                $table->dropIndex(['reseller_id']);
                $table->dropColumn('reseller_id');
            }
        });
    }
};
