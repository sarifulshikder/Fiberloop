<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the primary key, drop default, change id to UUID
        DB::statement('ALTER TABLE olt_ports DROP CONSTRAINT IF EXISTS olt_ports_pkey');
        DB::statement('ALTER TABLE olt_ports ALTER COLUMN id DROP DEFAULT');
        DB::statement('ALTER TABLE olt_ports ALTER COLUMN id TYPE uuid USING gen_random_uuid()');
        DB::statement('ALTER TABLE olt_ports ADD PRIMARY KEY (id)');

        // Update the column to use UUID generation for new records
        DB::statement('ALTER TABLE olt_ports ALTER COLUMN id SET DEFAULT gen_random_uuid()');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a destructive change - we can't easily reverse UUID to bigint
        // without data loss. Mark as irreversible.
        throw new \Exception('Cannot reverse UUID to bigint migration - would cause data loss');
    }
};
