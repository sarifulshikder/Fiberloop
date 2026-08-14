<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Make olt_ports.tenant_id nullable so single-tenant deployments (where
     * tenant_id is null across onus/olts/network_devices) can store OltPort
     * rows, matching the nullable tenant_id pattern used by the rest of the
     * network tables.
     */
    public function up(): void
    {
        Schema::table('olt_ports', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('olt_ports', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
        });
    }
};
