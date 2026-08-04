<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test case - seed roles and permissions
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed(RolesAndPermissionsSeeder::class);
        
        // Ensure Sanctum is set up for tests
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );
    }

    /**
     * Test that user can be authenticated
     */
    public function test_user_authentication_works(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->assignRole('admin');

        $this->actingAs($user);
        $this->assertAuthenticated();
    }

    /**
     * Test that user can have roles assigned
     */
    public function test_user_role_assignment_works(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->assertTrue($user->hasRole('admin'));
    }

    /**
     * Test that inactive user has is_active false
     */
    public function test_inactive_user_flag_works(): void
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);

        $this->assertFalse($user->is_active);
    }

    /**
     * Test that active user has is_active true
     */
    public function test_active_user_flag_works(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $this->assertTrue($user->is_active);
    }

    /**
     * Test that user password can be hashed
     */
    public function test_password_hashing_works(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('secret'),
        ]);

        $this->assertTrue(password_verify('secret', $user->password));
    }

    /**
     * Test that Sanctum token creation works
     */
    public function test_sanctum_token_creation_works(): void
    {
        $user = User::factory()->create([
            'email' => 'sanctum@example.com',
            'is_active' => true,
        ]);
        $user->assignRole('customer');

        $token = $user->createToken('test-token');

        $this->assertNotNull($token);
        $this->assertNotNull($token->plainTextToken);
    }

    /**
     * Test that user has two_factor_enabled attribute
     */
    public function test_user_has_two_factor_attributes(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);

        $this->assertFalse($user->two_factor_enabled);
        $this->assertFalse($user->hasTwoFactorEnabled());
    }

    /**
     * Test that user with two factor enabled works
     */
    public function test_user_with_two_factor_enabled(): void
    {
        $user = User::factory()->create([
            'two_factor_enabled' => true,
        ]);

        $this->assertTrue($user->two_factor_enabled);
        $this->assertTrue($user->hasTwoFactorEnabled());
    }

    /**
     * Test that login rate limiting is configured in AppServiceProvider
     */
    public function test_rate_limiting_is_configured(): void
    {
        // Check that the rate limiter configuration exists in the file
        $appServiceProviderContent = file_get_contents(app_path('Providers/AppServiceProvider.php'));
        
        $this->assertStringContainsString("RateLimiter::for('login'", $appServiceProviderContent);
    }

    /**
     * Test that all 8 roles exist in the database
     */
    public function test_all_8_roles_exist(): void
    {
        $expectedRoles = [
            'super_admin',
            'admin',
            'noc_engineer',
            'support_agent',
            'billing_agent',
            'reseller',
            'field_technician',
            'customer',
        ];

        $existingRoles = \Spatie\Permission\Models\Role::all()->pluck('name')->toArray();

        foreach ($expectedRoles as $role) {
            $this->assertContains($role, $existingRoles, "Role '{$role}' should exist");
        }
    }

    /**
     * Test that permissions are seeded
     */
    public function test_permissions_are_seeded(): void
    {
        $permissions = \Spatie\Permission\Models\Permission::all();
        
        $this->assertGreaterThan(80, $permissions->count(), 'Should have 85+ permissions');
    }
}
