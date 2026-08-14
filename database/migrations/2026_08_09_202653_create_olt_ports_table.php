<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('olt_ports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('olt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('network_device_id')->constrained()->cascadeOnDelete();

            // SNMP Interface identification
            $table->unsignedInteger('if_index')->comment('SNMP ifIndex');
            $table->string('name')->comment('ifDescr - e.g., GE1, SFP1, PON0/1');
            $table->string('alias')->nullable()->comment('ifAlias - user friendly name');
            $table->unsignedInteger('if_type')->nullable()->comment('ifType IANA number');
            $table->string('type_label')->nullable()->comment('Human readable: uplink, pon, access, mgmt');

            // Interface status
            $table->tinyInteger('admin_status')->nullable()->comment('1=up, 2=down, 3=testing');
            $table->tinyInteger('oper_status')->nullable()->comment('1=up, 2=down, 3=testing, 4=unknown, 5=dormant, 6=notPresent, 7=lowerLayerDown');
            $table->bigInteger('speed')->nullable()->comment('ifSpeed in bps');
            $table->unsignedInteger('high_speed')->nullable()->comment('ifHighSpeed in Mbps');
            $table->unsignedInteger('mtu')->nullable()->comment('ifMtu');
            $table->macAddress('mac_address')->nullable()->comment('ifPhysAddress');

            // Manual classification
            $table->boolean('is_uplink')->default(false)->comment('Manually marked as uplink port');
            $table->boolean('is_pon')->default(false)->comment('PON port (auto-detected or manual)');
            $table->boolean('is_active')->default(true);

            // SFP / Transceiver details (from ENTITY-MIB or vendor MIB)
            $table->boolean('sfp_present')->default(false);
            $table->string('sfp_vendor')->nullable();
            $table->string('sfp_part_number')->nullable();
            $table->string('sfp_serial_number')->nullable();
            $table->string('sfp_revision')->nullable();
            $table->string('sfp_date_code')->nullable();
            $table->string('sfp_connector_type')->nullable()->comment('LC, SC, MPO, etc.');
            $table->string('sfp_transceiver_code')->nullable()->comment('SFF-8472 compliance code');
            $table->string('sfp_encoding')->nullable()->comment('NRZ, PAM4, etc.');
            $table->string('sfp_wavelength')->nullable()->comment('e.g., 1310nm, 1550nm, 1490/1310nm');
            $table->string('sfp_distance')->nullable()->comment('e.g., 10km, 20km, 40km');
            $table->string('sfp_standard')->nullable()->comment('1000BASE-LX, 10GBASE-LR, GPON, XGS-PON, etc.');

            // SFP DOM (Digital Optical Monitoring) - real-time
            $table->decimal('sfp_tx_power_dbm', 6, 2)->nullable()->comment('Tx optical power dBm');
            $table->decimal('sfp_rx_power_dbm', 6, 2)->nullable()->comment('Rx optical power dBm');
            $table->decimal('sfp_temperature_c', 5, 2)->nullable()->comment('Module temperature Celsius');
            $table->decimal('sfp_voltage_v', 5, 3)->nullable()->comment('Supply voltage Volts');
            $table->decimal('sfp_tx_bias_ma', 6, 2)->nullable()->comment('Tx bias current mA');
            $table->decimal('sfp_rx_power_mw', 8, 4)->nullable()->comment('Rx power in mW (calculated)');
            $table->decimal('sfp_tx_power_mw', 8, 4)->nullable()->comment('Tx power in mW (calculated)');

            // SFP Alarm/Warning thresholds (from SFF-8472)
            $table->json('sfp_thresholds')->nullable()->comment('High/low alarm/warning thresholds for temp, voltage, tx/rx power, bias');

            // SFP Status flags
            $table->json('sfp_alarms')->nullable()->comment('Active alarms: tx_power_high, rx_power_low, temp_high, etc.');
            $table->json('sfp_warnings')->nullable()->comment('Active warnings');

            // Port counters (from IF-MIB / EtherLike-MIB)
            $table->bigInteger('if_in_octets')->nullable()->comment('ifInOctets');
            $table->bigInteger('if_out_octets')->nullable()->comment('ifOutOctets');
            $table->bigInteger('if_in_errors')->nullable()->comment('ifInErrors');
            $table->bigInteger('if_out_errors')->nullable()->comment('ifOutErrors');
            $table->bigInteger('if_in_discards')->nullable()->comment('ifInDiscards');
            $table->bigInteger('if_out_discards')->nullable()->comment('ifOutDiscards');
            $table->bigInteger('if_in_ucast_pkts')->nullable()->comment('ifInUcastPkts');
            $table->bigInteger('if_out_ucast_pkts')->nullable()->comment('ifOutUcastPkts');

            // Uptime / last change
            $table->unsignedInteger('if_last_change')->nullable()->comment('ifLastChange in timeticks');
            $table->timestamp('link_up_since')->nullable()->comment('Calculated from ifLastChange when oper_status=up');
            $table->string('uptime_string')->nullable()->comment('Human readable uptime');

            // Polling metadata
            $table->timestamp('last_polled_at')->nullable();
            $table->boolean('poll_error')->default(false);
            $table->text('poll_error_message')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['olt_id', 'if_index'], 'olt_ports_olt_ifindex_unique');
            $table->index(['olt_id', 'is_uplink']);
            $table->index(['olt_id', 'oper_status']);
            $table->index(['tenant_id', 'is_uplink']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olt_ports');
    }
};
