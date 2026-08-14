<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE network_devices DROP CONSTRAINT network_devices_vendor_check');
        DB::statement("ALTER TABLE network_devices ADD CONSTRAINT network_devices_vendor_check CHECK (vendor::text = ANY (ARRAY['mikrotik'::character varying, 'huawei'::character varying, 'zte'::character varying, 'nokia'::character varying, 'cisco'::character varying, 'vsol'::character varying, 'bdcom'::character varying, 'other'::character varying]::text[]))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE network_devices DROP CONSTRAINT network_devices_vendor_check');
        DB::statement("ALTER TABLE network_devices ADD CONSTRAINT network_devices_vendor_check CHECK (vendor::text = ANY (ARRAY['mikrotik'::character varying, 'huawei'::character varying, 'zte'::character varying, 'nokia'::character varying, 'cisco'::character varying, 'other'::character varying]::text[]))");
    }
};
