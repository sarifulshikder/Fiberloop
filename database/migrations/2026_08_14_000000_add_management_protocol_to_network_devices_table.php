<?php

use App\Enums\NetworkManagementProtocol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Add the management protocol used to talk to this device (SNMP vs SSH CLI).
     * Defaults to SNMP for backward compatibility with existing devices.
     */
    public function up(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            $table->enum('management_protocol', NetworkManagementProtocol::values())
                ->default(NetworkManagementProtocol::SNMP->value)
                ->after('hostname');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            $table->dropColumn('management_protocol');
        });
    }
};
