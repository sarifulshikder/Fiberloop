<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            $table->integer('ssh_port')->nullable()->default(22)->after('port');
            $table->integer('telnet_port')->nullable()->after('ssh_port');
        });

        // Backfill telnet_port from the legacy configuration.telnet_port JSON values.
        DB::table('network_devices')
            ->whereNotNull('configuration')
            ->get(['id', 'configuration'])
            ->each(function (stdClass $row) {
                $config = json_decode($row->configuration, true);

                if (is_array($config) && isset($config['telnet_port'])) {
                    DB::table('network_devices')->where('id', $row->id)->update([
                        'telnet_port' => (int) $config['telnet_port'],
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('network_devices', function (Blueprint $table) {
            $table->dropColumn(['ssh_port', 'telnet_port']);
        });
    }
};
