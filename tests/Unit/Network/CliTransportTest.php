<?php

namespace Tests\Unit\Network;

use App\Models\NetworkDevice;
use App\Services\Network\CliTransport;
use RuntimeException;
use Tests\TestCase;

class CliTransportTest extends TestCase
{
    private function device(array $attrs = []): NetworkDevice
    {
        return new NetworkDevice(array_merge([
            'ip_address' => '127.0.0.1',
            'port' => 1, // closed port -> immediate connection refused
            'username' => 'admin',
            'password' => 'secret',
        ], $attrs));
    }

    public function test_connect_fails_cleanly_when_unreachable(): void
    {
        $transport = new CliTransport($this->device());

        $this->assertFalse($transport->connect());
        $this->assertFalse($transport->isConnected());
    }

    public function test_connect_fails_when_credentials_missing(): void
    {
        $transport = new CliTransport($this->device(['username' => null]));

        $this->assertFalse($transport->connect());
    }

    public function test_exec_throws_when_not_connected(): void
    {
        $transport = new CliTransport($this->device());

        $this->expectException(RuntimeException::class);
        $transport->exec('show version');
    }

    public function test_disconnect_is_idempotent(): void
    {
        $transport = new CliTransport($this->device());

        $transport->disconnect();
        $transport->disconnect();

        $this->assertFalse($transport->isConnected());
    }
}
