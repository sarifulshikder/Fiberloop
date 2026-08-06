<?php

namespace Tests\Unit\Radius;

use App\Models\RadAcct;
use App\Models\User;
use App\Services\Radius\RadiusCoaService;
use App\Services\Radius\RadiusSessionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadiusSessionServiceTest extends TestCase
{
    use RefreshDatabase;

    private RadiusSessionService $sessionService;

    protected function setUp(): void
    {
        parent::setUp();
        User::factory()->create(['id' => 1]);
        $coaService = new RadiusCoaService();
        $this->sessionService = new RadiusSessionService($coaService);
    }

    public function test_get_active_sessions_returns_only_open_sessions(): void
    {
        // Open session (no stoptime)
        RadAcct::create([
            'acctsessionid' => 'active_001',
            'acctuniqueid' => 'uniq_active_001',
            'username' => 'testuser1',
            'nasipaddress' => '10.0.0.1',
            'acctstarttime' => Carbon::now()->subHour(),
            'acctstoptime' => null,
        ]);

        // Closed session (has stoptime)
        RadAcct::create([
            'acctsessionid' => 'closed_001',
            'acctuniqueid' => 'uniq_closed_001',
            'username' => 'testuser2',
            'nasipaddress' => '10.0.0.1',
            'acctstarttime' => Carbon::now()->subDays(2),
            'acctstoptime' => Carbon::now()->subDay(),
        ]);

        $activeSessions = $this->sessionService->getActiveSessions(100);

        $usernames = collect($activeSessions->items())->pluck('username');
        $this->assertTrue($usernames->contains('testuser1'));
        $this->assertFalse($usernames->contains('testuser2'));
    }

    public function test_get_customer_active_session_returns_correct_session(): void
    {
        RadAcct::create([
            'acctsessionid' => 'cust_active_001',
            'acctuniqueid' => 'uniq_cust_001',
            'username' => 'customer_a',
            'nasipaddress' => '10.0.0.2',
            'acctstarttime' => Carbon::now()->subMinutes(30),
            'acctstoptime' => null,
        ]);

        $session = $this->sessionService->getCustomerActiveSession('customer_a');

        $this->assertNotNull($session);
        $this->assertEquals('cust_active_001', $session->acctsessionid);
    }

    public function test_get_customer_active_session_returns_null_when_no_active_session(): void
    {
        $session = $this->sessionService->getCustomerActiveSession('nonexistent_user');
        $this->assertNull($session);
    }

    public function test_usage_stats_calculates_bytes_correctly(): void
    {
        RadAcct::create([
            'acctsessionid' => 'usage_001',
            'acctuniqueid' => 'uniq_usage_001',
            'username' => 'datauser',
            'nasipaddress' => '10.0.0.3',
            'acctstarttime' => Carbon::now()->subDays(3),
            'acctinputoctets' => 1073741824, // 1 GB
            'acctoutputoctets' => 2147483648, // 2 GB
            'acctsessiontime' => 7200, // 2 hours
        ]);

        $stats = $this->sessionService->getUserUsageStats(
            'datauser',
            Carbon::now()->subWeek(),
            Carbon::now()
        );

        $this->assertEquals(1073741824, $stats['input_bytes']);
        $this->assertEquals(2147483648, $stats['output_bytes']);
        $this->assertEquals(3221225472, $stats['total_bytes']);
        $this->assertEquals('1 GB', $stats['input_formatted']);
        $this->assertEquals('2 GB', $stats['output_formatted']);
        $this->assertEquals(7200, $stats['total_session_seconds']);
        $this->assertEquals(2.0, $stats['total_session_hours']);
        $this->assertEquals(1, $stats['session_count']);
    }

    public function test_format_bytes_returns_human_readable(): void
    {
        $this->assertEquals('1 GB', $this->sessionService->formatBytes(1073741824));
        $this->assertEquals('500 MB', $this->sessionService->formatBytes(524288000));
        $this->assertEquals('1.5 GB', $this->sessionService->formatBytes(1610612736));
    }
}
