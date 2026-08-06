<?php

namespace Tests\Unit\Radius;

use App\Models\Nas;
use App\Models\RadCheck;
use App\Models\RadiusUser;
use App\Models\RadReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadiusModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_rad_check_creation_and_query(): void
    {
        $radCheck = RadCheck::create([
            'username' => 'testuser@fiberloop.local',
            'attribute' => 'Cleartext-Password',
            'op' => ':=',
            'value' => 'secret123',
        ]);

        $this->assertDatabaseHas('radcheck', [
            'username' => 'testuser@fiberloop.local',
            'attribute' => 'Cleartext-Password',
            'value' => 'secret123',
        ], 'radius');
    }

    public function test_rad_reply_creation_and_query(): void
    {
        $radReply = RadReply::create([
            'username' => 'testuser@fiberloop.local',
            'attribute' => 'Mikrotik-Rate-Limit',
            'op' => '=',
            'value' => '10M/10M',
        ]);

        $this->assertDatabaseHas('radreply', [
            'username' => 'testuser@fiberloop.local',
            'attribute' => 'Mikrotik-Rate-Limit',
            'value' => '10M/10M',
        ], 'radius');
    }

    public function test_nas_creation_and_encrypted_secret(): void
    {
        $nas = Nas::create([
            'nasname' => '192.168.1.1',
            'shortname' => 'core-router-1',
            'type' => 'mikrotik',
            'secret' => 'super-secret-nas-key',
            'description' => 'Main PPPoE NAS Router',
        ]);

        $this->assertDatabaseHas('nas', [
            'nasname' => '192.168.1.1',
            'shortname' => 'core-router-1',
        ], 'radius');

        $retrieved = Nas::find($nas->id);
        $this->assertEquals('super-secret-nas-key', $retrieved->secret);
    }

    public function test_radius_user_relationship_to_rad_reply(): void
    {
        RadCheck::create([
            'username' => 'user2@fiberloop.local',
            'attribute' => 'Cleartext-Password',
            'op' => ':=',
            'value' => 'pass123',
        ]);

        RadReply::create([
            'username' => 'user2@fiberloop.local',
            'attribute' => 'Framed-IP-Address',
            'op' => '=',
            'value' => '10.10.0.50',
        ]);

        $radiusUser = RadiusUser::where('username', 'user2@fiberloop.local')->first();
        $this->assertNotNull($radiusUser);
        $this->assertCount(1, $radiusUser->replies);
        $this->assertEquals('10.10.0.50', $radiusUser->replies->first()->value);
    }
}
