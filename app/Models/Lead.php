<?php

namespace App\Models;

use App\Enums\ConnectionType;
use App\Enums\CustomerStatus;
use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;


    protected $fillable = [
        'tenant_id',
        'uuid',
        'created_by',
        'updated_by',
        'assigned_to',
        'first_name',
        'last_name',
        'email',
        'phone',
        'alternate_phone',
        'address',
        'latitude',
        'longitude',
        'area',
        'zone',
        'status',
        'is_feasible',
        'assigned_olt_id',
        'assigned_network_device_id',
        'feasibility_notes',
        'site_survey_date',
        'converted_customer_id',
        'converted_at',
        'source',
        'referral_code',
        'notes',
        'priority',
    ];

    protected $casts = [
        'status' => LeadStatus::class,
        'is_feasible' => 'boolean',
        'site_survey_date' => 'datetime',
        'converted_at' => 'datetime',
        'latitude' => 'string',
        'longitude' => 'string',
    ];

    protected $appends = [
        'full_name',
    ];

    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedOlt(): BelongsTo
    {
        return $this->belongsTo(Olt::class, 'assigned_olt_id');
    }

    public function assignedNetworkDevice(): BelongsTo
    {
        return $this->belongsTo(NetworkDevice::class, 'assigned_network_device_id');
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    // Scopes
    public function scopeByTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByStatus($query, LeadStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeNew($query)
    {
        return $query->where('status', LeadStatus::NEW);
    }

    public function scopeContacted($query)
    {
        return $query->where('status', LeadStatus::CONTACTED);
    }

    public function scopeSiteSurvey($query)
    {
        return $query->where('status', LeadStatus::SITE_SURVEY);
    }

    public function scopeConverted($query)
    {
        return $query->where('status', LeadStatus::CONVERTED);
    }

    public function scopeLost($query)
    {
        return $query->where('status', LeadStatus::LOST);
    }

    public function scopeByArea($query, $area)
    {
        return $query->where('area', $area);
    }

    public function scopeByZone($query, $zone)
    {
        return $query->where('zone', $zone);
    }

    public function scopeFeasible($query)
    {
        return $query->where('is_feasible', true);
    }

    public function scopeNotFeasible($query)
    {
        return $query->where('is_feasible', false);
    }

    // Status transition methods
    public function markAsContacted(): void
    {
        $this->forceFill(['status' => LeadStatus::CONTACTED])->save();

        activity()
            ->by($this->updatedBy ?? $this->createdBy)
            ->on($this)
            ->withProperties(['old_status' => $this->getOriginal('status'), 'new_status' => LeadStatus::CONTACTED->value])
            ->log('Lead marked as contacted');
    }

    public function markAsSiteSurvey(): void
    {
        $this->forceFill(['status' => LeadStatus::SITE_SURVEY])->save();

        activity()
            ->by($this->updatedBy ?? $this->createdBy)
            ->on($this)
            ->withProperties(['old_status' => $this->getOriginal('status'), 'new_status' => LeadStatus::SITE_SURVEY->value])
            ->log('Lead marked for site survey');
    }

    public function markAsConverted(Customer $customer): void
    {
        $this->forceFill([
            'status' => LeadStatus::CONVERTED,
            'converted_customer_id' => $customer->id,
            'converted_at' => now(),
        ])->save();

        activity()
            ->by($this->updatedBy ?? $this->createdBy)
            ->on($this)
            ->withProperties(['customer_id' => $customer->id, 'old_status' => $this->getOriginal('status')])
            ->log('Lead converted to customer');
    }

    public function markAsLost(?string $reason = null): void
    {
        $this->forceFill(['status' => LeadStatus::LOST])->save();

        $properties = ['old_status' => $this->getOriginal('status')];
        if ($reason) {
            $this->forceFill(['notes' => ($this->notes ? $this->notes . '\n' : '') . 'Lost reason: ' . $reason])->save();
            $properties['reason'] = $reason;
        }

        activity()
            ->by($this->updatedBy ?? $this->createdBy)
            ->on($this)
            ->withProperties($properties)
            ->log('Lead marked as lost');
    }

    public function checkFeasibility(): bool
    {
        if ($this->is_feasible !== null) {
            return $this->is_feasible;
        }
        if ($this->assigned_olt_id !== null) {
            return true;
        }
        if ($this->assigned_network_device_id !== null) {
            return true;
        }
        return \App\Models\Olt::query()
            ->where('area', $this->area)
            ->where('zone', $this->zone)
            ->exists() || \App\Models\NetworkDevice::query()
            ->where('area', $this->area)
            ->where('zone', $this->zone)
            ->exists();
    }

    /**
     * Convert this lead to a customer
     */
    public function convertToCustomer(array $customerData = []): Customer
    {
        $actor = $this->updatedBy ?? $this->createdBy ?? auth()->user();

        $customer = Customer::create(array_merge([
            'tenant_id' => $this->tenant_id,
            'uuid' => \Illuminate\Support\Str::uuid(),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'alternate_phone' => $this->alternate_phone,
            'service_address' => $this->address,
            'service_latitude' => $this->latitude,
            'service_longitude' => $this->longitude,
            'billing_address' => $this->address,
            'area' => $this->area,
            'zone' => $this->zone,
            'connection_type' => ConnectionType::PPPOE,
            'status' => CustomerStatus::PENDING,
            'lead_id' => $this->id,
        ], $customerData));

        // Mark lead as converted
        $this->markAsConverted($customer);

        // Log the conversion
        activity()
            ->by($actor)
            ->on($customer)
            ->withProperties(['lead_id' => $this->id, 'lead_status' => $this->status->value])
            ->log('Customer created from lead conversion');

        return $customer->fresh();
    }

    /**
     * Check if this lead can be converted (must be feasible or have required fields)
     */
    public function canBeConverted(): bool
    {
        if ($this->status === LeadStatus::CONVERTED) {
            return false;
        }

        if ($this->status === LeadStatus::LOST) {
            return false;
        }

        // Must have at minimum: first_name, last_name, phone
        if (empty($this->first_name) || empty($this->last_name) || empty($this->phone)) {
            return false;
        }

        return true;
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
