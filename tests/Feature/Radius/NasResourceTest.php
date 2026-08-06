<?php

namespace Tests\Feature\Radius;

use App\Models\Nas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NasResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['id' => 1]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_nas_creation_and_encrypted_secret(): void
    {
        $nas = Nas::create([
            'nasname' => '10.0.0.1',
            'shortname' => 'nas-dhaka-1',
            'type' => 'mikrotik',
            'secret' => 'super-secret-key-123',
            'description' => 'Dhaka POP Router',
        ]);

        $this->assertDatabaseHas('nas', [
            'nasname' => '10.0.0.1',
            'shortname' => 'nas-dhaka-1',
        ], 'radius');

        $retrieved = Nas::where('nasname', '10.0.0.1')->first();
        $this->assertEquals('super-secret-key-123', $retrieved->secret);
    }
}
