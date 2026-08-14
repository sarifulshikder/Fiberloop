<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE network_devices ALTER COLUMN port SET DEFAULT 8728');
        DB::statement('ALTER TABLE network_devices ALTER COLUMN ssh_port SET DEFAULT 22');
        DB::statement('ALTER TABLE network_devices ALTER COLUMN telnet_port SET DEFAULT 23');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE network_devices ALTER COLUMN port SET DEFAULT 22');
        DB::statement('ALTER TABLE network_devices ALTER COLUMN ssh_port SET DEFAULT 22');
        DB::statement('ALTER TABLE network_devices ALTER COLUMN telnet_port DROP DEFAULT');
    }
};
