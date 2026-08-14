<?php

namespace App\Services\Network\OltDrivers;

/**
 * BDCOM OLT (P33xx series, Cisco-like CLI) driver reading ONU data over SSH.
 */
class BdcomCliDriver extends CliOltDriver
{
    protected function vendorKey(): string
    {
        return 'bdcom';
    }

    protected function hasAutofindCommand(): bool
    {
        return false;
    }
}
