<?php

namespace Tests\Unit\Radius;

use App\Jobs\Radius\EnforceFairUsagePolicy;
use App\Models\Customer;
use App\Models\Package;
use App\Models\RadAcct;
use App\Models\RadReply;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Radius\RadiusCoaService;
use App\Services\Radius\RadiusProvisioningService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnforceFairUsagePolicyTest extends TestCase
{
    use RefreshDatabase;

    private RadiusProvisioningService $provisioningService;
    private RadiusCoaService $coaService;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(['id' => 1]);
        $this->provisioningService = new RadiusProvisioningService();
        $this->coaService = new RadiusCoaService();
    }

    public function test_fup_throttles_bandwidth_when_threshold_exceeded(): void
    {
        // 1. Package with 100GB FUP threshold
        $package = Package::factory()->create([
            'download_speed' => 50,
            'upload_speed' => 20,
            'fup_threshold' => 100, // 100 GB
            'fup_throttled_download' => 5,
            'fup_throttled_upload' => 2,
        ]);

        $customer = Customer::factory()->create(['phone' => '01900000001']);
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'active',
            'start_date' => Carbon::now()->startOfMonth(),
        ]);

        $this->provisioningService->provisionUser($customer, $subscription, ['radius_password' => 'secret']);

        // Initial rate limit should be 20M/50M
        $this->assertDatabaseHas('radreply', [
            'username' => '01900000001',
            'attribute' => 'Mikrotik-Rate-Limit',
            'value' => '20M/50M',
        ], 'radius');

        // 2. Add radacct session exceeding 100 GB (110 GB = 118111600640 bytes)
        RadAcct::create([
            'acctsessionid' => 'sess_001',
            'acctuniqueid' => 'uniq_001',
            'username' => '01900000001',
            'acctstarttime' => Carbon::now()->subDays(2),
            'acctinputoctets' => 60000000000,
            'acctoutputoctets' => 60000000000,
        ]);

        // 3. Execute FUP enforcement job
        $job = new EnforceFairUsagePolicy();
        $job->handle($this->provisioningService, $this->coaService);

        // 4. Rate limit should now be throttled to 2M/5M
        $this->assertDatabaseHas('radreply', [
            'username' => '01900000001',
            'attribute' => 'Mikrotik-Rate-Limit',
            'value' => '2M/5M',
        ], 'radius');
    }

    public function test_fup_does_not_throttle_when_under_threshold(): void
    {
        $package = Package::factory()->create([
            'download_speed' => 30,
            'upload_speed' => 10,
            'fup_threshold' => 500, // 500 GB
            'fup_throttled_download' => 3,
            'fup_throttled_upload' => 1,
        ]);

        $customer = Customer::factory()->create(['phone' => '01900000002']);
        $subscription = Subscription::factory()->create([
            'customer_id' => $customer->id,
            'package_id' => $package->id,
            'status' => 'active',
        ]);

        $this->provisioningService->provisionUser($customer, $subscription, ['radius_password' => 'secret']);

        // Usage 10 GB (under 500 GB threshold)
        RadAcct::create([
            'acctsessionid' => 'sess_002',
            'acctuniqueid' => 'uniq_002',
            'username' => '01900000002',
            'acctstarttime' => Carbon::now()->subDay(),
            'acctinputoctets' => 5000000000,
            'acctoutputoctets' => 5000000000,
        ]);

        $job = new EnforceFairUsagePolicy();
        $job->handle($this->provisioningService, $this->coaService);

        // Should keep normal speed 10M/30M
        $this->assertDatabaseHas('radreply', [
            'username' => '01900000002',
            'attribute' => 'Mikrotik-Rate-Limit',
            'value' => '10M/30M',
        ], 'radius');
    }
}
