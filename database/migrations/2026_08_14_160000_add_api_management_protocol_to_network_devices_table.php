<?php

use App\Enums\NetworkManagementProtocol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Allow MikroTik devices to be managed over the RouterOS API in addition
     * to SNMP/SSH CLI, by extending the management_protocol check constraint.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE network_devices DROP CONSTRAINT network_devices_management_protocol_check');

        $allowed = implode(', ', array_map(
            fn (string $value) => "'{$value}'::character varying",
            NetworkManagementProtocol::values(),
        ));

        DB::statement(
            "ALTER TABLE network_devices ADD CONSTRAINT network_devices_management_protocol_check "
            ."CHECK ((management_protocol)::text = ANY (ARRAY[{$allowed}]))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE network_devices DROP CONSTRAINT network_devices_management_protocol_check');

        DB::table('network_devices')
            ->where('management_protocol', NetworkManagementProtocol::API->value)
            ->update(['management_protocol' => NetworkManagementProtocol::SNMP->value]);

        DB::statement(
            "ALTER TABLE network_devices ADD CONSTRAINT network_devices_management_protocol_check "
            ."CHECK ((management_protocol)::text = ANY (ARRAY['snmp'::character varying, 'ssh'::character varying]))"
        );
    }
};
