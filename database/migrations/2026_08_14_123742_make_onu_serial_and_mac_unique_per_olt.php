<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A physical ONU can be visible from more than one OLT record (e.g. two OLT
 * records at the same management IP expose the same device), so serial/MAC
 * uniqueness must be scoped to the OLT, not global. The existing rows were
 * verified duplicate-free within each OLT before this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onus', function (Blueprint $table) {
            $table->dropUnique(['serial_number']);
            $table->dropUnique(['mac_address']);

            $table->unique(['olt_id', 'serial_number']);
            $table->unique(['olt_id', 'mac_address']);
        });
    }

    public function down(): void
    {
        Schema::table('onus', function (Blueprint $table) {
            $table->dropUnique(['olt_id', 'serial_number']);
            $table->dropUnique(['olt_id', 'mac_address']);

            $table->unique(['serial_number']);
            $table->unique(['mac_address']);
        });
    }
};
