<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-tenant payment gateway configuration.
 *
 * Overrides the static values in config/payment-gateways.php at runtime.
 * Credentials (app keys, secrets, merchant ids) are encrypted at rest.
 */
class PaymentGatewaySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'gateway',
        'enabled',
        'sandbox',
        'credentials',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'enabled' => 'boolean',
        'sandbox' => 'boolean',
        'credentials' => 'encrypted:array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    /**
     * Get the merged effective configuration for a gateway,
     * layering DB-stored credentials/flags over config/payment-gateways.php.
     */
    public static function mergedConfig(string $gateway): array
    {
        $base = config("payment-gateways.{$gateway}", []);

        $setting = static::query()
            ->where('gateway', $gateway)
            ->orderByRaw('tenant_id IS NULL ASC')
            ->first();

        if ($setting === null) {
            return $base;
        }

        return array_merge(
            $base,
            $setting->credentials ?? [],
            [
                'enabled' => $setting->enabled,
                'sandbox' => $setting->sandbox,
            ]
        );
    }
}
