<?php

namespace App\Services\Network\OltDrivers;

use App\Models\OltPort;
use App\Services\Network\OltCliOutputParser;
use App\Services\Network\TelnetTransport;
use Illuminate\Support\Collection;

/**
 * BDCOM OLT (P33xx series, Cisco-like CLI) driver.
 *
 * The BDCOM management CLI is only reachable over plain telnet (Switch>
 * / Switch# prompt) and exposes no SNMP service on its management IP (the
 * SNMP service there belongs to the upstream VSOL OLT), so both ONU sync and
 * "Poll Ports" run over the telnet session.
 */
class BdcomCliDriver extends CliOltDriver implements SupportsCliPortPoll
{
    protected function vendorKey(): string
    {
        return 'bdcom';
    }

    protected function hasAutofindCommand(): bool
    {
        return false;
    }

    protected function buildTransport(): TelnetTransport
    {
        $device = $this->olt->networkDevice;

        return new TelnetTransport(
            $device,
            port: (int) ($device->telnet_port ?? config('olt.telnet_port', 23)),
            promptPattern: 'Switch(?:\(config[^)]*\))?[#>]',
            loginPromptPattern: '/username:\s*$/i',
        );
    }

    /**
     * BDCOM commands take the port as `epon0/%s`, i.e. just the port number
     * ("1".."4") rather than the "0/1" form.
     */
    protected function ponPortIdentifiers(): array
    {
        $ports = $this->olt->configuration['pon_ports'] ?? null;

        if (is_array($ports) && $ports !== []) {
            return array_map(fn (string $port) => (string) OltCliOutputParser::portInt($port), $ports);
        }

        $total = (int) ($this->olt->total_pon_ports ?? 0);

        if ($total <= 0) {
            $total = (int) config('olt.default_pon_ports', 4);
        }

        return array_map(fn (int $i) => (string) $i, range(1, $total));
    }

    /**
     * The P3310C ships 6 GE/combo ports (GigaEthernet0/1 - 0/6); 0/7 and 0/8
     * do not exist, unlike the VSOL default of 8.
     */
    protected function gigabitPortIdentifiers(): array
    {
        $ports = $this->olt->configuration['gigabit_ports'] ?? null;

        if (is_array($ports) && $ports !== []) {
            return array_map(fn (string $port) => (string) OltCliOutputParser::portInt($port), $ports);
        }

        $total = (int) ($this->olt->configuration['gigabit_port_count'] ?? 6);

        return array_map(fn (int $i) => (string) $i, range(1, $total));
    }

    /**
     * BDCOM running config uses one interface block per ONU
     * (`interface EPON0/1:1` + `description <name>`), not the VSOL
     * "interface epon 0/X / onu N description" grouping.
     */
    protected function descriptionsMap(): array
    {
        if ($this->descriptionsCache !== null) {
            return $this->descriptionsCache;
        }

        $this->descriptionsCache = [];

        $command = config("olt.commands.{$this->vendorKey()}.onu_descriptions");

        if ($command === null) {
            return $this->descriptionsCache;
        }

        $output = $this->runCommand($command);

        if ($output !== '') {
            $this->descriptionsCache = OltCliOutputParser::parseBdcomDescriptionsTable($output);
        }

        return $this->descriptionsCache;
    }

    /**
     * The BDCOM OLT has no reachable SNMP service, so "Poll Ports" reads PON
     * and gigabit/uplink port status over the telnet CLI.
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

        $uplinkPorts = collect($this->olt->configuration['uplink_ports'] ?? [])
            ->map(fn (string $port) => (string) OltCliOutputParser::portInt($port))
            ->all();

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

                $info = OltCliOutputParser::parseBdcomInterfaceInfo($output);

                [$portCreated, $portUpdated] = $this->upsertPort($existingPorts, [
                    'olt_id' => $this->olt->id,
                    'tenant_id' => $this->olt->tenant_id,
                    'network_device_id' => $device->id,
                    'if_index' => OltCliOutputParser::portInt((string) $port),
                    'name' => 'EPON0/' . $port,
                    'alias' => $info['description'],
                    'type_label' => 'pon',
                    'is_pon' => true,
                    'is_uplink' => false,
                    'is_active' => true,
                    'admin_status' => $info['admin_status'] ?? 1,
                    'oper_status' => $info['state'],
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

                $info = OltCliOutputParser::parseBdcomInterfaceInfo($output);
                $portNumber = OltCliOutputParser::portInt((string) $port);
                $description = $info['description'];
                $isUplink = in_array((string) $portNumber, $uplinkPorts, true)
                    || ($description !== null && preg_match('/link|uplink/i', $description) === 1);

                [$portCreated, $portUpdated] = $this->upsertPort($existingPorts, [
                    'olt_id' => $this->olt->id,
                    'tenant_id' => $this->olt->tenant_id,
                    'network_device_id' => $device->id,
                    // GE ports get an offset ifIndex (100+) so they never clash
                    // with the PON ports (1-4).
                    'if_index' => 100 + $portNumber,
                    'name' => 'GE0/' . $port,
                    'alias' => $description,
                    'type_label' => $isUplink ? 'uplink' : ($info['state'] === 1 ? 'access' : 'other'),
                    'is_pon' => false,
                    'is_uplink' => $isUplink,
                    'is_active' => true,
                    'admin_status' => $info['admin_status'] ?? 1,
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
