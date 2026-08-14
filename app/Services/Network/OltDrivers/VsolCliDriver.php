<?php

namespace App\Services\Network\OltDrivers;

use App\Models\OltPort;
use App\Services\Network\OltCliOutputParser;
use App\Services\Network\TelnetTransport;
use Illuminate\Support\Collection;

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
            port: (int) ($device->telnet_port ?? config('olt.telnet_port', 23)),
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
     * and gigabit/uplink port status over the telnet CLI instead of IF-MIB
     * walks.
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

        $ponCommand = config("olt.commands.{$this->vendorKey()}.pon_info");
        $gigabitCommand = config("olt.commands.{$this->vendorKey()}.gigabit_info");

        if ($ponCommand === null && $gigabitCommand === null) {
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

        if ($ponCommand !== null) {
            foreach ($this->ponPortIdentifiers() as $port) {
                $output = $this->runCommand(sprintf($ponCommand, $port));

                if ($output === '') {
                    continue;
                }

                $status = OltCliOutputParser::parsePonInfo($output);
                $ifIndex = OltCliOutputParser::portInt((string) $port);

                [$portCreated, $portUpdated] = $this->upsertPort($existingPorts, [
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
                ]);
                $created += $portCreated;
                $updated += $portUpdated;

                $polled++;
            }
        }

        if ($gigabitCommand !== null) {
            foreach ($this->gigabitPortIdentifiers() as $port) {
                $output = $this->runCommand(sprintf($gigabitCommand, $port));

                if ($output === '') {
                    continue;
                }

                $info = OltCliOutputParser::parseGigabitethernetInfo($output);
                $portNumber = OltCliOutputParser::portInt((string) $port);
                $description = $info['description'];
                $isUplink = $description !== null && stripos($description, 'link') !== false;

                [$portCreated, $portUpdated] = $this->upsertPort($existingPorts, [
                    'olt_id' => $this->olt->id,
                    'tenant_id' => $this->olt->tenant_id,
                    'network_device_id' => $device->id,
                    // GE ports get an offset ifIndex (100+) so they never clash
                    // with the PON ports (1-4).
                    'if_index' => 100 + $portNumber,
                    'name' => 'GE' . $port,
                    'alias' => $description,
                    'type_label' => $isUplink ? 'uplink' : ($info['state'] === 1 ? 'access' : 'other'),
                    'is_pon' => false,
                    'is_uplink' => $isUplink,
                    'is_active' => true,
                    'admin_status' => 1,
                    'oper_status' => $info['state'],
                    'speed' => $info['high_speed'] !== null ? $info['high_speed'] * 1_000_000 : null,
                    'high_speed' => $info['high_speed'],
                    'mtu' => $info['mtu'],
                    'last_polled_at' => now(),
                    'poll_error' => false,
                    'poll_error_message' => null,
                ]);
                $created += $portCreated;
                $updated += $portUpdated;

                $polled++;
            }
        }

        return [
            'polled' => $polled,
            'created' => $created,
            'updated' => $updated,
            'reachable' => true,
        ];
    }

    protected function gigabitPortIdentifiers(): array
    {
        $ports = $this->olt->configuration['gigabit_ports'] ?? null;

        if (is_array($ports) && $ports !== []) {
            return $ports;
        }

        $total = (int) ($this->olt->total_gigabit_ports ?? config('olt.default_gigabit_ports', 8));

        return array_map(fn (int $i) => '0/' . $i, range(1, $total));
    }

    private function upsertPort(Collection $existingPorts, array $attributes): array
    {
        $ifIndex = $attributes['if_index'];

        if (isset($existingPorts[$ifIndex])) {
            $existingPorts[$ifIndex]->update($attributes);

            return [0, 1];
        }

        OltPort::create($attributes);

        return [1, 0];
    }
}
