<?php

namespace App\Services\Network\OltDrivers;

use App\Models\OltPort;
use App\Services\Network\OltCliOutputParser;
use App\Services\Network\TelnetTransport;

/**
 * VSOL OLT (V1600D EPON / V2600G GPON) driver.
 *
 * Unlike Huawei/ZTE, the VSOL CLI is reachable over plain telnet and ONU
 * commands only exist inside the per-port context `interface epon 0/X`.
 * Each command in config/olt.php is therefore a full navigation sequence
 * (configure terminal -> interface epon %s -> show ... -> exit), executed
 * over a stateful telnet session.
 */
class VsolCliDriver extends CliOltDriver implements SupportsCliPortPoll
{
    protected function vendorKey(): string
    {
        return 'vsol';
    }

    protected function hasAutofindCommand(): bool
    {
        return true;
    }

    protected function buildTransport(): TelnetTransport
    {
        $device = $this->olt->networkDevice;

        return new TelnetTransport(
            $device,
            port: (int) ($device->configuration['telnet_port'] ?? config('olt.telnet_port', 23)),
        );
    }

    protected function ponPortIdentifiers(): array
    {
        $ports = $this->olt->configuration['pon_ports'] ?? null;

        if (is_array($ports) && $ports !== []) {
            return $ports;
        }

        $total = (int) ($this->olt->total_pon_ports ?? 0);

        if ($total <= 0) {
            // VSOL V1600D ships 4 EPON ports by default.
            $total = (int) config('olt.default_pon_ports', 4);
        }

        return array_map(fn (int $i) => '0/' . $i, range(1, $total));
    }

    /**
     * The VSOL OLT has no reachable SNMP service, so "Poll Ports" reads PON
     * port status over the telnet CLI instead of IF-MIB walks.
     */
    public function pollPorts(): array
    {
        $device = $this->olt->networkDevice;

        if (!$device || !$device->is_active) {
            return [
                'polled' => 0,
                'created' => 0,
                'updated' => 0,
                'reachable' => false,
            ];
        }

        $command = config("olt.commands.{$this->vendorKey()}.pon_info");

        if ($command === null) {
            return [
                'polled' => 0,
                'created' => 0,
                'updated' => 0,
                'reachable' => false,
            ];
        }

        $existingPorts = OltPort::where('olt_id', $this->olt->id)->get()->keyBy('if_index');
        $created = 0;
        $updated = 0;
        $polled = 0;

        foreach ($this->ponPortIdentifiers() as $port) {
            $output = $this->runCommand(sprintf($command, $port));

            if ($output === '') {
                continue;
            }

            $status = OltCliOutputParser::parsePonInfo($output);
            $ifIndex = OltCliOutputParser::portInt((string) $port);

            $attributes = [
                'olt_id' => $this->olt->id,
                'tenant_id' => $this->olt->tenant_id,
                'network_device_id' => $device->id,
                'if_index' => $ifIndex,
                'name' => 'EPON' . $port,
                'type_label' => 'pon',
                'is_pon' => true,
                'is_uplink' => false,
                'is_active' => true,
                'admin_status' => $status['admin_status'],
                'oper_status' => $status['oper_status'],
                'last_polled_at' => now(),
                'poll_error' => false,
                'poll_error_message' => null,
            ];

            if (isset($existingPorts[$ifIndex])) {
                $existingPorts[$ifIndex]->update($attributes);
                $updated++;
            } else {
                OltPort::create($attributes);
                $created++;
            }

            $polled++;
        }

        return [
            'polled' => $polled,
            'created' => $created,
            'updated' => $updated,
            'reachable' => true,
        ];
    }
}
