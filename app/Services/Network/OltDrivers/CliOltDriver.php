<?php

namespace App\Services\Network\OltDrivers;

use App\Models\Olt;
use App\Services\Network\CliTransport;
use App\Services\Network\OltCliOutputParser;
use App\Services\Network\SnmpService;
use App\Services\Network\TelnetTransport;
use Throwable;

/**
 * Base driver that reads OLT data over SSH CLI instead of vendor MIBs.
 *
 * The flow is identical for every vendor:
 *   1. issue the vendor's ONU-info command(s) and parse the table
 *   2. issue the vendor's optical-power command(s) once and cache the result
 *   3. serve getOnuRxPower()/getOnuTxPower()/isOnuOnline() from that cache
 *
 * Only the commands (config/olt.php) and the PON-port identifiers differ.
 */
abstract class CliOltDriver implements OltDriverInterface
{
    protected CliTransport|TelnetTransport $transport;

    protected ?array $signalCache = null;

    protected ?array $basicInfoCache = null;

    protected ?array $descriptionsCache = null;

    public function __construct(protected Olt $olt)
    {
        $this->transport = $this->buildTransport();
    }

    /**
     * Build the CLI transport for this OLT. Subclasses override to use a
     * different session protocol (e.g. VSOL uses telnet).
     */
    protected function buildTransport(): CliTransport|TelnetTransport
    {
        return new CliTransport($this->olt->networkDevice);
    }

    /**
     * Config key for this vendor's command table (config/olt.php).
     */
    abstract protected function vendorKey(): string;

    /**
     * Whether the vendor reports the "autofind"/unconfigured ONUs separately
     * and an extra discovery command should be issued.
     */
    abstract protected function hasAutofindCommand(): bool;

    /**
     * PON port identifiers to poll. Single-command vendors (VSOL, BDCOM)
     * return [null]; multi-port vendors (Huawei, ZTE) return per-port IDs.
     */
    protected function ponPortIdentifiers(): array
    {
        return [null];
    }

    public function discoverOnus(): array
    {
        $signals = $this->signalMap();
        $basicInfo = $this->basicInfoMap();
        $descriptions = $this->descriptionsMap();
        $onus = [];

        foreach ($this->ponPortIdentifiers() as $port) {
            foreach ($this->discoveryCommands($port) as $command) {
                $output = $this->runCommand($command);

                if ($output === '') {
                    continue;
                }

                foreach (OltCliOutputParser::parseOnuTable($output, $port) as $info) {
                    $key = $this->signalKey($info->ponPort, $info->onuId);
                    $signal = $signals[$key] ?? [];
                    $basic = $basicInfo[$key] ?? [];

                    $onus[] = $info->with([
                        'rx_power_dbm' => $signal['rx_power_dbm'] ?? $info->rxPowerDbm,
                        'tx_power_dbm' => $signal['tx_power_dbm'] ?? $info->txPowerDbm,
                        // The ONU-info table is authoritative for online state;
                        // the optical table's is_online is merely "has power".
                        'is_online' => $info->isOnline,
                        'pon_port_name' => $info->ponPortName ?? ($info->ponPort !== null ? 'PON ' . $info->ponPort : null),
                        'vendor_id' => $basic['vendor_id'] ?? $this->vendorKey(),
                        'firmware_version' => $basic['firmware_version'] ?? null,
                        'hardware_version' => $basic['hardware_version'] ?? null,
                        'ONU_type' => $basic['ONU_type'] ?? null,
                        'customer_name' => $descriptions[$key] ?? null,
                    ])->toArray();
                }
            }
        }

        return $onus;
    }

    public function getOnuRxPower(string $ponPort, string $onuMacOrId): ?float
    {
        return $this->signalMap()[$this->signalKey($ponPort, $onuMacOrId)]['rx_power_dbm'] ?? null;
    }

    public function getOnuTxPower(string $ponPort, string $onuMacOrId): ?float
    {
        return $this->signalMap()[$this->signalKey($ponPort, $onuMacOrId)]['tx_power_dbm'] ?? null;
    }

    public function isOnuOnline(string $ponPort, string $onuMacOrId): bool
    {
        $row = $this->signalMap()[$this->signalKey($ponPort, $onuMacOrId)] ?? null;

        if ($row === null) {
            return false;
        }

        return (bool) ($row['is_online'] ?? ($row['rx_power_dbm'] !== null));
    }

    /**
     * SNMP remains available for interface/port polling even when ONU optics
     * are read over CLI (most OLTs run both services).
     */
    public function getSnmpService(): SnmpService
    {
        $device = $this->olt->networkDevice;

        return new SnmpService(
            $device->ip_address,
            $device->snmp_community ?? 'public',
            $device->snmp_version ?? '2c',
            $device->snmp_port ?? 161
        );
    }

    public function getSfpDomData(): array
    {
        // SFP DOM is not parsed from CLI output yet; vendor SNMP drivers handle it.
        return [];
    }

    protected function discoveryCommands(?string $port): array
    {
        $commands = [];

        $info = config("olt.commands.{$this->vendorKey()}.onu_info");
        if ($info !== null) {
            $commands[] = sprintf($info, $port ?? '');
        }

        if ($this->hasAutofindCommand()) {
            $autofind = config("olt.commands.{$this->vendorKey()}.onu_autofind")
                ?? config("olt.commands.{$this->vendorKey()}.onu_uncfg");

            if ($autofind !== null) {
                $commands[] = sprintf($autofind, $port ?? '');
            }
        }

        return $commands;
    }

    protected function opticalCommands(?string $port): array
    {
        $command = config("olt.commands.{$this->vendorKey()}.onu_optical");

        if ($command === null) {
            return [];
        }

        return [sprintf($command, $port ?? '')];
    }

    protected function signalMap(): array
    {
        if ($this->signalCache !== null) {
            return $this->signalCache;
        }

        $this->signalCache = [];

        foreach ($this->ponPortIdentifiers() as $port) {
            foreach ($this->opticalCommands($port) as $command) {
                $output = $this->runCommand($command);

                if ($output === '') {
                    continue;
                }

                foreach (OltCliOutputParser::parseOpticalTable($output, $port) as $key => $row) {
                    $this->signalCache[$key] = $row;
                }
            }
        }

        return $this->signalCache;
    }

    /**
     * Vendor static ONU data (vendor/model/hw/sw/type) from `onu_basic_info`,
     * keyed by "{port}|{onu_id}". Per-port for vendors whose commands need a
     * port context; issued once and cached.
     */
    protected function basicInfoMap(): array
    {
        if ($this->basicInfoCache !== null) {
            return $this->basicInfoCache;
        }

        $this->basicInfoCache = [];

        $command = config("olt.commands.{$this->vendorKey()}.onu_basic_info");

        if ($command === null) {
            return $this->basicInfoCache;
        }

        foreach ($this->ponPortIdentifiers() as $port) {
            $output = $this->runCommand(sprintf($command, $port ?? ''));

            if ($output === '') {
                continue;
            }

            foreach (OltCliOutputParser::parseBasicInfoTable($output) as $key => $row) {
                $this->basicInfoCache[$key] = $row;
            }
        }

        return $this->basicInfoCache;
    }

    /**
     * ONU descriptions (customer names) from the vendor's `onu_descriptions`
     * command, keyed by "{port}|{onu_id}".
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
            $this->descriptionsCache = OltCliOutputParser::parseDescriptionsTable($output);
        }

        return $this->descriptionsCache;
    }

    protected function signalKey(string|int|null $ponPort, string|int|null $onuId): string
    {
        return (string) ($ponPort ?? '0') . '|' . (string) ($onuId ?? '?');
    }

    protected function runCommand(string $command): string
    {
        try {
            return $this->transport->exec($command);
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('CliOltDriver: command failed', [
                'command' => $command,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }
}
