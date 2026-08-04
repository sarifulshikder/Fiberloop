<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('onus', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('olt_id');
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            // ONU identification
            $table->string('serial_number')->unique();
            $table->string('mac_address')->nullable()->unique();
            $table->string('ONU_id')->nullable()->comment('ONU identifier on the OLT');
            $table->integer('pon_port')->nullable()->comment('PON port number');
            $table->string('pon_port_name')->nullable();

            // Registration
            $table->string('registration_id')->nullable()->unique();
            $table->dateTime('registered_at')->nullable();
            $table->boolean('is_registered')->default(false);

            // Signal levels
            $table->decimal('optical_signal_db', 5, 2)->nullable()->comment('Optical signal level in dBm');
            $table->decimal('tx_power_db', 5, 2)->nullable()->comment('Transmit power in dBm');
            $table->decimal('rx_power_db', 5, 2)->nullable()->comment('Receive power in dBm');

            // ONU info
            $table->string('vendor_id')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('hardware_version')->nullable();
            $table->string('ONU_type')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->string('operational_state')->nullable();
            $table->dateTime('last_signal_check_at')->nullable();

            // Distance
            $table->unsignedInteger('distance_meters')->nullable()->comment('Distance from OLT in meters');

            // Metadata
            $table->json('configuration')->nullable();
            $table->text('notes')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'olt_id']);
            $table->index(['olt_id', 'serial_number']);
            $table->index('customer_id');
            $table->index('subscription_id');
            $table->index('serial_number');
            $table->index('mac_address');
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onus');
    }
};
