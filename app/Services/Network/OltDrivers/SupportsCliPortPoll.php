<?php

namespace App\Services\Network\OltDrivers;

/**
 * Marker for CLI/telnet drivers that can poll port status directly over the
 * OLT CLI, used when the OLT has no reachable SNMP service.
 */
interface SupportsCliPortPoll
{
    /**
     * Poll PON/interface port status over the CLI and upsert OltPort records.
     *
     * Returns:
     * [
     *   'polled'   => int,
     *   'created'  => int,
     *   'updated'  => int,
     *   'reachable'=> bool,
     * ]
     */
    public function pollPorts(): array;
}
