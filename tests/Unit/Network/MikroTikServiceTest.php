<?php

namespace Tests\Unit\Network;

use App\Models\NetworkDevice;
use App\Services\Network\MikroTikService;
use Mockery;
use RouterosAPI;
use Tests\TestCase;

class MikroTikServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createMockClient(): Mockery\MockInterface
    {
        $mockClient = Mockery::mock(RouterosAPI::class);
        $mockClient->debug = false;
        $mockClient->timeout = 5;
        // The destructor calls disconnect if connected is true, so we allow it
        $mockClient->shouldReceive('disconnect')->byDefault();
        return $mockClient;
    }

    public function test_connect_success(): void
    {
        $device = new NetworkDevice([
            'ip_address' => '192.168.88.1',
            'username' => 'admin',
            'password' => 'secret',
            'api_port' => 8728,
        ]);

        $mockClient = $this->createMockClient();
        $mockClient->shouldReceive('connect')
            ->once()
            ->with('192.168.88.1', 'admin', 'secret', 8728)
            ->andReturn(true);

        $service = new MikroTikService($device, $mockClient);
        $this->assertTrue($service->connect());
    }

    public function test_connect_failure(): void
    {
        $device = new NetworkDevice([
            'ip_address' => '192.168.88.1',
            'username' => 'admin',
            'password' => 'wrong',
            'api_port' => 8728,
        ]);

        $mockClient = $this->createMockClient();
        $mockClient->shouldReceive('connect')
            ->once()
            ->with('192.168.88.1', 'admin', 'wrong', 8728)
            ->andReturn(false);

        $service = new MikroTikService($device, $mockClient);
        $this->assertFalse($service->connect());
    }

    public function test_get_system_resource(): void
    {
        $device = new NetworkDevice(['ip_address' => '192.168.88.1', 'username' => 'admin']);
        $mockClient = $this->createMockClient();

        $mockClient->shouldReceive('connect')->andReturn(true);
        $mockClient->shouldReceive('write')->with('/system/resource/print')->once();
        $mockClient->shouldReceive('read')->with(false)->once()->andReturn(['raw_response']);
        $mockClient->shouldReceive('parseResponse')->with(['raw_response'])->once()->andReturn([
            ['uptime' => '5d4h', 'version' => '7.1']
        ]);

        $service = new MikroTikService($device, $mockClient);
        $resource = $service->getSystemResource();

        $this->assertNotNull($resource);
        $this->assertEquals('5d4h', $resource['uptime']);
    }

    public function test_get_active_pppoe_sessions(): void
    {
        $device = new NetworkDevice(['ip_address' => '192.168.88.1', 'username' => 'admin']);
        $mockClient = $this->createMockClient();

        $mockClient->shouldReceive('connect')->andReturn(true);
        $mockClient->shouldReceive('write')->with('/ppp/active/print')->once();
        $mockClient->shouldReceive('read')->with(false)->once()->andReturn(['raw_sessions']);
        $mockClient->shouldReceive('parseResponse')->with(['raw_sessions'])->once()->andReturn([
            ['.id' => '*1', 'name' => 'user1', 'service' => 'pppoe']
        ]);

        $service = new MikroTikService($device, $mockClient);
        $sessions = $service->getActivePppoeSessions();

        $this->assertCount(1, $sessions);
        $this->assertEquals('user1', $sessions[0]['name']);
    }

    public function test_disconnect_pppoe_session(): void
    {
        $device = new NetworkDevice(['ip_address' => '192.168.88.1', 'username' => 'admin']);
        $mockClient = $this->createMockClient();

        $mockClient->shouldReceive('connect')->andReturn(true);
        // Find session
        $mockClient->shouldReceive('write')->with('/ppp/active/print', false)->once();
        $mockClient->shouldReceive('write')->with('?name=user1')->once();
        $mockClient->shouldReceive('read')->with(false)->once()->andReturn(['raw_session']);
        $mockClient->shouldReceive('parseResponse')->with(['raw_session'])->once()->andReturn([
            ['.id' => '*1', 'name' => 'user1']
        ]);
        // Remove session
        $mockClient->shouldReceive('write')->with('/ppp/active/remove', false)->once();
        $mockClient->shouldReceive('write')->with('=.id=*1')->once();
        $mockClient->shouldReceive('read')->with(false)->once();

        $service = new MikroTikService($device, $mockClient);
        $result = $service->disconnectPppoeSession('user1');

        $this->assertTrue($result);
    }

    public function test_set_simple_queue_add(): void
    {
        $device = new NetworkDevice(['ip_address' => '192.168.88.1', 'username' => 'admin']);
        $mockClient = $this->createMockClient();

        $mockClient->shouldReceive('connect')->andReturn(true);
        // Check if queue exists
        $mockClient->shouldReceive('write')->with('/queue/simple/print', false)->once();
        $mockClient->shouldReceive('write')->with('?name=queue1')->once();
        $mockClient->shouldReceive('read')->with(false)->once()->andReturn([]);
        $mockClient->shouldReceive('parseResponse')->once()->andReturn([]);
        // Add queue
        $mockClient->shouldReceive('write')->with('/queue/simple/add', false)->once();
        $mockClient->shouldReceive('write')->with('=name=queue1', false)->once();
        $mockClient->shouldReceive('write')->with('=target=192.168.1.10', false)->once();
        $mockClient->shouldReceive('write')->with('=max-limit=10M/10M')->once();
        $mockClient->shouldReceive('read')->with(false)->once();

        $service = new MikroTikService($device, $mockClient);
        $result = $service->setSimpleQueue('queue1', '192.168.1.10', '10M/10M');

        $this->assertTrue($result);
    }

    public function test_set_ppp_secret_add(): void
    {
        $device = new NetworkDevice(['ip_address' => '192.168.88.1', 'username' => 'admin']);
        $mockClient = $this->createMockClient();

        $mockClient->shouldReceive('connect')->andReturn(true);
        // Secret does not exist yet
        $mockClient->shouldReceive('write')->with('/ppp/secret/print', false)->once();
        $mockClient->shouldReceive('write')->with('?name=user1')->once();
        $mockClient->shouldReceive('read')->with(false)->once()->andReturn(['raw']);
        $mockClient->shouldReceive('parseResponse')->with(['raw'])->once()->andReturn([]);
        // Add it
        $mockClient->shouldReceive('write')->with('/ppp/secret/add', false)->once();
        $mockClient->shouldReceive('write')->with('=name=user1', false)->once();
        $mockClient->shouldReceive('write')->with('=password=pass123', false)->once();
        $mockClient->shouldReceive('write')->with('=service=pppoe', false)->once();
        $mockClient->shouldReceive('write')->with('=disabled=no', false)->once();
        $mockClient->shouldReceive('write')->with('=profile=fiberloop-20M-10M', false)->once();
        $mockClient->shouldReceive('write')->with('=remote-address=192.168.1.10')->once();
        $mockClient->shouldReceive('read')->with(false)->once();

        $service = new MikroTikService($device, $mockClient);
        $result = $service->setPppSecret('user1', 'pass123', 'fiberloop-20M-10M', '192.168.1.10', false);

        $this->assertTrue($result);
    }

    public function test_set_ppp_secret_update(): void
    {
        $device = new NetworkDevice(['ip_address' => '192.168.88.1', 'username' => 'admin']);
        $mockClient = $this->createMockClient();

        $mockClient->shouldReceive('connect')->andReturn(true);
        // Secret already exists
        $mockClient->shouldReceive('write')->with('/ppp/secret/print', false)->once();
        $mockClient->shouldReceive('write')->with('?name=user1')->once();
        $mockClient->shouldReceive('read')->with(false)->once()->andReturn(['raw']);
        $mockClient->shouldReceive('parseResponse')->with(['raw'])->once()->andReturn([
            ['.id' => '*1', 'name' => 'user1'],
        ]);
        // Update it
        $mockClient->shouldReceive('write')->with('/ppp/secret/set', false)->once();
        $mockClient->shouldReceive('write')->with('=.id=*1', false)->once();
        $mockClient->shouldReceive('write')->with('=name=user1', false)->once();
        $mockClient->shouldReceive('write')->with('=password=pass123', false)->once();
        $mockClient->shouldReceive('write')->with('=service=pppoe', false)->once();
        $mockClient->shouldReceive('write')->with('=disabled=no')->once();
        $mockClient->shouldReceive('read')->with(false)->once();

        $service = new MikroTikService($device, $mockClient);
        $result = $service->setPppSecret('user1', 'pass123');

        $this->assertTrue($result);
    }

    public function test_set_ppp_secret_enabled_disables_secret(): void
    {
        $device = new NetworkDevice(['ip_address' => '192.168.88.1', 'username' => 'admin']);
        $mockClient = $this->createMockClient();

        $mockClient->shouldReceive('connect')->andReturn(true);
        $mockClient->shouldReceive('write')->with('/ppp/secret/print', false)->once();
        $mockClient->shouldReceive('write')->with('?name=user1')->once();
        $mockClient->shouldReceive('read')->with(false)->once()->andReturn(['raw']);
        $mockClient->shouldReceive('parseResponse')->with(['raw'])->once()->andReturn([
            ['.id' => '*1'],
        ]);
        $mockClient->shouldReceive('write')->with('/ppp/secret/set', false)->once();
        $mockClient->shouldReceive('write')->with('=.id=*1', false)->once();
        $mockClient->shouldReceive('write')->with('=disabled=yes')->once();
        $mockClient->shouldReceive('read')->with(false)->once();

        $service = new MikroTikService($device, $mockClient);
        $result = $service->setPppSecretEnabled('user1', false);

        $this->assertTrue($result);
    }

    public function test_remove_ppp_secret(): void
    {
        $device = new NetworkDevice(['ip_address' => '192.168.88.1', 'username' => 'admin']);
        $mockClient = $this->createMockClient();

        $mockClient->shouldReceive('connect')->andReturn(true);
        $mockClient->shouldReceive('write')->with('/ppp/secret/print', false)->once();
        $mockClient->shouldReceive('write')->with('?name=user1')->once();
        $mockClient->shouldReceive('read')->with(false)->once()->andReturn(['raw']);
        $mockClient->shouldReceive('parseResponse')->with(['raw'])->once()->andReturn([
            ['.id' => '*1'],
        ]);
        $mockClient->shouldReceive('write')->with('/ppp/secret/remove', false)->once();
        $mockClient->shouldReceive('write')->with('=.id=*1')->once();
        $mockClient->shouldReceive('read')->with(false)->once();

        $service = new MikroTikService($device, $mockClient);
        $result = $service->removePppSecret('user1');

        $this->assertTrue($result);
    }

    public function test_ensure_ppp_profile_adds_missing_profile(): void
    {
        $device = new NetworkDevice(['ip_address' => '192.168.88.1', 'username' => 'admin']);
        $mockClient = $this->createMockClient();

        $mockClient->shouldReceive('connect')->andReturn(true);
        $mockClient->shouldReceive('write')->with('/ppp/profile/print', false)->once();
        $mockClient->shouldReceive('write')->with('?name=fiberloop-20M-10M')->once();
        $mockClient->shouldReceive('read')->with(false)->once()->andReturn(['raw']);
        $mockClient->shouldReceive('parseResponse')->with(['raw'])->once()->andReturn([]);
        $mockClient->shouldReceive('write')->with('/ppp/profile/add', false)->once();
        $mockClient->shouldReceive('write')->with('=name=fiberloop-20M-10M', false)->once();
        $mockClient->shouldReceive('write')->with('=rate-limit=10M/20M')->once();
        $mockClient->shouldReceive('read')->with(false)->once();

        $service = new MikroTikService($device, $mockClient);
        $profile = $service->ensurePppProfile(20, 10);

        $this->assertEquals('fiberloop-20M-10M', $profile);
    }

    public function test_ensure_ppp_profile_returns_existing(): void
    {
        $device = new NetworkDevice(['ip_address' => '192.168.88.1', 'username' => 'admin']);
        $mockClient = $this->createMockClient();

        $mockClient->shouldReceive('connect')->andReturn(true);
        $mockClient->shouldReceive('write')->with('/ppp/profile/print', false)->once();
        $mockClient->shouldReceive('write')->with('?name=fiberloop-20M-10M')->once();
        $mockClient->shouldReceive('read')->with(false)->once()->andReturn(['raw']);
        $mockClient->shouldReceive('parseResponse')->with(['raw'])->once()->andReturn([
            ['.id' => '*1', 'name' => 'fiberloop-20M-10M'],
        ]);

        $service = new MikroTikService($device, $mockClient);
        $profile = $service->ensurePppProfile(20, 10);

        $this->assertEquals('fiberloop-20M-10M', $profile);
    }

    public function test_reboot(): void
    {
        $device = new NetworkDevice(['ip_address' => '192.168.88.1', 'username' => 'admin']);
        $mockClient = $this->createMockClient();

        $mockClient->shouldReceive('connect')->andReturn(true);
        $mockClient->shouldReceive('write')->with('/system/reboot')->once();
        $mockClient->shouldReceive('read')->with(false)->once();

        $service = new MikroTikService($device, $mockClient);
        $result = $service->reboot();

        $this->assertTrue($result);
    }
}
