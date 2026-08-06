<?php

namespace Tests\Unit\Network;

use App\Jobs\PollDeviceMetricsJob;
use App\Models\DeviceMetric;
use App\Models\NetworkDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PollDeviceMetricsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // NetworkDeviceFactory hardcodes created_by/updated_by = 1
        \App\Models\User::factory()->create(['id' => 1]);
    }

    private function makeDevice(array $attrs = []): NetworkDevice
    {
        return NetworkDevice::factory()->create(array_merge([
            'ip_address' => '127.0.0.1', // always reachable on loopback
            'is_active' => true,
            'snmp_community' => null, // disable SNMP path for unit tests
        ], $attrs));
    }

    public function test_stores_device_metric_row_after_poll(): void
    {
        $device = $this->makeDevice();

        // Dispatch synchronously
        PollDeviceMetricsJob::dispatchSync($device);

        $this->assertDatabaseHas('device_metrics', [
            'network_device_id' => $device->id,
        ]);
    }

    public function test_marks_device_reachable_when_up(): void
    {
        $device = $this->makeDevice(['ip_address' => '127.0.0.1']);

        PollDeviceMetricsJob::dispatchSync($device);

        $device->refresh();
        // 127.0.0.1 should always ping successfully in the container
        $this->assertNotNull($device->last_checked_at);
    }

    public function test_uptime_parser_via_reflection(): void
    {
        $device = $this->makeDevice();
        $job = new PollDeviceMetricsJob($device);

        $ref = new \ReflectionMethod($job, 'parseUptime');
        $ref->setAccessible(true);

        $this->assertEquals(0 + 4 * 3600 + 3 * 60 + 2, $ref->invoke($job, '4h3m2s'));
        $this->assertEquals(5 * 86400, $ref->invoke($job, '5d'));
        $this->assertEquals(1 * 604800 + 2 * 86400, $ref->invoke($job, '1w2d'));
        $this->assertNull($ref->invoke($job, null));
        $this->assertNull($ref->invoke($job, ''));
    }

    public function test_status_resolved_as_down_when_ping_null(): void
    {
        $device = $this->makeDevice();
        $job = new PollDeviceMetricsJob($device);

        $ref = new \ReflectionMethod($job, 'resolveStatus');
        $ref->setAccessible(true);

        $this->assertEquals('down', $ref->invoke($job, null));
        $this->assertEquals('up', $ref->invoke($job, 10));
        $this->assertEquals('degraded', $ref->invoke($job, 250));
    }

    public function test_mem_usage_calc_via_reflection(): void
    {
        $device = $this->makeDevice();
        $job = new PollDeviceMetricsJob($device);

        $ref = new \ReflectionMethod($job, 'calcMemUsagePercent');
        $ref->setAccessible(true);

        $this->assertEquals(50.0, $ref->invoke($job, ['total-memory' => 1000, 'free-memory' => 500]));
        $this->assertEquals(100.0, $ref->invoke($job, ['total-memory' => 1000, 'free-memory' => 0]));
        $this->assertNull($ref->invoke($job, ['total-memory' => 0, 'free-memory' => 0]));
    }

    public function test_stores_second_metric_row_on_repeat_poll(): void
    {
        $device = $this->makeDevice();

        PollDeviceMetricsJob::dispatchSync($device);
        PollDeviceMetricsJob::dispatchSync($device);

        // Each poll should insert a new time-series row (no upsert)
        $this->assertGreaterThanOrEqual(2, DeviceMetric::where('network_device_id', $device->id)->count());
    }
}
