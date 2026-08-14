<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ports are blank by default; the admin fills in a port only when the
        // device actually needs one. All transport layers fall back to sane
        // defaults (API 8728, SSH 22/port, telnet 23, SNMP 161) when unset.
        DB::statement('ALTER TABLE network_devices ALTER COLUMN port DROP NOT NULL');

        foreach (['port', 'ssh_port', 'telnet_port', 'snmp_port'] as $column) {
            DB::statement("ALTER TABLE network_devices ALTER COLUMN {$column} DROP DEFAULT");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE network_devices ALTER COLUMN port SET DEFAULT 8728');
        DB::statement('ALTER TABLE network_devices ALTER COLUMN ssh_port SET DEFAULT 22');
        DB::statement('ALTER TABLE network_devices ALTER COLUMN telnet_port SET DEFAULT 23');
        DB::statement('ALTER TABLE network_devices ALTER COLUMN snmp_port SET DEFAULT 161');
        DB::statement('ALTER TABLE network_devices ALTER COLUMN port SET NOT NULL');
    }
};
