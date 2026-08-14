<?php

use App\Enums\ProvisioningMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('provisioning_method')
                ->default(ProvisioningMethod::RADIUS->value)
                ->after('connection_type');
            $table->foreignId('network_device_id')
                ->nullable()
                ->after('provisioning_method')
                ->constrained('network_devices')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['network_device_id']);
            $table->dropColumn(['provisioning_method', 'network_device_id']);
        });
    }
};
