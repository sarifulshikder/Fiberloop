<?php

use App\Filament\Pages\PaymentGatewaySettings;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Services\Payments\IdempotencyService;
use App\Services\Payments\SSLCommerzService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use ReflectionClass;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    filament()->setCurrentPanel(filament()->getPanel('admin'));
});

it('renders the payment gateway settings page for admins', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    $this->get(PaymentGatewaySettings::getUrl())
        ->assertSuccessful()
        ->assertSee('bKash')
        ->assertSee('SSLCommerz');
});

it('blocks non-admin roles from the settings page', function () {
    $user = User::factory()->create()->assignRole('noc_engineer');
    $this->actingAs($user);

    $this->get(PaymentGatewaySettings::getUrl())->assertForbidden();
});

it('prefills the form from existing settings', function () {
    PaymentGatewaySetting::create([
        'gateway' => 'bkash',
        'enabled' => true,
        'sandbox' => false,
        'credentials' => ['app_key' => 'existing-key'],
    ]);

    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    Livewire::test(PaymentGatewaySettings::class)
        ->assertFormSet([
            'bkash_enabled' => true,
            'bkash_sandbox' => false,
            'bkash_app_key' => 'existing-key',
        ]);
});

it('persists gateway credentials from the form', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    Livewire::test(PaymentGatewaySettings::class)
        ->fillForm([
            'bkash_enabled' => true,
            'bkash_sandbox' => false,
            'bkash_app_key' => 'sandbox-app-key',
            'bkash_app_secret' => 'sandbox-app-secret',
            'bkash_username' => 'sandbox-user',
            'bkash_password' => 'sandbox-pass',
            'bkash_merchant_id' => 'MERCHANT001',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('payment_gateway_settings', [
        'gateway' => 'bkash',
        'enabled' => true,
        'sandbox' => false,
    ]);

    $settings = PaymentGatewaySetting::where('gateway', 'bkash')->first();

    expect($settings->credentials)->toBeArray()
        ->and($settings->credentials['app_key'])->toBe('sandbox-app-key')
        ->and($settings->credentials['app_secret'])->toBe('sandbox-app-secret')
        ->and($settings->credentials['username'])->toBe('sandbox-user')
        ->and($settings->credentials['merchant_id'])->toBe('MERCHANT001');
});

it('stores credentials encrypted at rest', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    Livewire::test(PaymentGatewaySettings::class)
        ->fillForm([
            'bkash_enabled' => true,
            'bkash_app_key' => 'SECRET_KEY_123',
        ])
        ->call('save');

    $settings = PaymentGatewaySetting::where('gateway', 'bkash')->first();
    expect($settings->credentials['app_key'])->toBe('SECRET_KEY_123');

    $raw = DB::table('payment_gateway_settings')->where('gateway', 'bkash')->value('credentials');
    expect($raw)->not->toContain('SECRET_KEY_123');
});

it('keeps existing credential values when left blank on save', function () {
    PaymentGatewaySetting::create([
        'gateway' => 'nagad',
        'enabled' => true,
        'credentials' => ['api_key' => 'keep-me'],
    ]);

    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    Livewire::test(PaymentGatewaySettings::class)
        ->fillForm([
            'nagad_enabled' => true,
            'nagad_api_key' => '',
        ])
        ->call('save');

    $settings = PaymentGatewaySetting::where('gateway', 'nagad')->first();
    expect($settings->credentials['api_key'])->toBe('keep-me');
});

it('merges db settings over static config for gateway services', function () {
    PaymentGatewaySetting::create([
        'gateway' => 'sslcommerz',
        'enabled' => true,
        'sandbox' => true,
        'credentials' => ['store_id' => 'DBSTORE', 'store_password' => 'DBPASS'],
    ]);

    $service = new SSLCommerzService(app(IdempotencyService::class));
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('config');
    $config = $method->invoke($service);

    expect($config['store_id'])->toBe('DBSTORE')
        ->and($config['store_password'])->toBe('DBPASS')
        ->and($config['enabled'])->toBeTrue()
        ->and($config['sandbox'])->toBeTrue();
});
