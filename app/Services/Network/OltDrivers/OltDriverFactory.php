<?php

namespace App\Services\Network\OltDrivers;

use App\Models\Olt;
use InvalidArgumentException;

class OltDriverFactory
{
    public static function make(Olt $olt): OltDriverInterface
    {
        // Infer vendor from the related NetworkDevice
        $vendor = strtolower($olt->networkDevice->vendor?->value ?? '');

        return match ($vendor) {
            'vsol' => new VsolDriver($olt),
            'bdcom' => new BdcomDriver($olt),
            default => throw new InvalidArgumentException("No OLT driver found for vendor: {$vendor}"),
        };
    }
}
