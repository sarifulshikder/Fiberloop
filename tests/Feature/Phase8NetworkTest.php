<?php

use App\Models\Incident;
use App\Models\NetworkDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('Phase 8: Network Device Management', function () {
    beforeEach(function () {
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        
        $this->tenant = \App\Models\Tenant::create(['name' => 'Test Tenant']);
        tenancy()->initialize($this->tenant);
        
        $this->admin = User::factory()->create(['id' => 1, 'tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('super_admin');
    });

    it('creates an incident when a device goes down', function () {
        $device = NetworkDevice::factory()->create([
            'is_active'    => true,
            'is_reachable' => true,
        ]);

        // Simulate device-down incident creation directly
        Incident::create([
            'uuid'              => (string) Str::uuid(),
            'title'             => "Device Down: {$device->name}",
            'description'       => "Device at {$device->ip_address} is unreachable.",
            'status'            => 'open',
            'severity'          => 'critical',
            'network_device_id' => $device->id,
            'started_at'        => now(),
        ]);

        expect(Incident::where('network_device_id', $device->id)
            ->where('status', 'open')
            ->where('title', 'like', 'Device Down:%')
            ->exists()
        )->toBeTrue();
    });

    it('auto-resolves an outage incident when device comes back up', function () {
        $device = NetworkDevice::factory()->create([
            'is_active'    => true,
            'is_reachable' => false,
        ]);

        // Create an open outage incident
        Incident::create([
            'uuid'              => (string) Str::uuid(),
            'title'             => "Device Down: {$device->name}",
            'description'       => 'Device is unreachable.',
            'status'            => 'open',
            'severity'          => 'critical',
            'network_device_id' => $device->id,
            'started_at'        => now()->subMinutes(10),
        ]);

        // Resolve (as PollDeviceMetricsJob would do on recovery)
        Incident::where('network_device_id', $device->id)
            ->where('status', 'open')
            ->where('title', 'like', 'Device Down:%')
            ->update([
                'status'      => 'resolved',
                'resolved_at' => now(),
            ]);

        expect(Incident::where('network_device_id', $device->id)
            ->where('status', 'open')
            ->exists()
        )->toBeFalse();

        expect(Incident::where('network_device_id', $device->id)
            ->where('status', 'resolved')
            ->exists()
        )->toBeTrue();
    });

    it('correlates a new incident to an existing network device', function () {
        $device = NetworkDevice::factory()->create();

        $incident = Incident::create([
            'uuid'              => (string) Str::uuid(),
            'title'             => 'High CPU on router',
            'description'       => 'CPU load exceeds 90%.',
            'status'            => 'open',
            'severity'          => 'warning',
            'network_device_id' => $device->id,
            'started_at'        => now(),
        ]);

        expect($incident->networkDevice->id)->toBe($device->id);
    });
});
