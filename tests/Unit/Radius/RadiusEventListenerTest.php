<?php

namespace Tests\Unit\Radius;

use App\Events\Billing\SubscriptionReactivated;
use App\Events\Billing\SubscriptionSuspended;
use App\Events\Billing\SubscriptionTerminated;
use App\Listeners\Radius\HandleSubscriptionReactivated;
use App\Listeners\Radius\HandleSubscriptionSuspended;
use App\Listeners\Radius\HandleSubscriptionTerminated;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RadiusCustomer;
use App\Models\User;
use App\Services\Network\SubscriberProvisioningService;
use App\Services\Radius\RadiusCoaService;
use App\Services\Radius\RadiusProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RadiusEventListenerTest extends TestCase
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

    private function provisioning(): SubscriberProvisioningService
    {
        return new SubscriberProvisioningService($this->provisioningService);
    }

    public function test_suspended_event_listener_disables_radius_user(): void
    {
        $customer = Customer::factory()->create(['phone' => '01800000001']);
        $this->provisioningService->provisionUser($customer, null, ['radius_password' => 'secret']);

        $invoice = Invoice::factory()->create(['customer_id' => $customer->id]);
        $event = new SubscriptionSuspended($customer, $invoice, 'Overdue payment');

        $listener = new HandleSubscriptionSuspended($this->provisioning(), $this->coaService);
        $listener->handle($event);

        $this->assertDatabaseHas('radcheck', [
            'username' => '01800000001',
            'attribute' => 'Auth-Type',
            'value' => 'Reject',
        ], 'radius');

        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();
        $this->assertFalse($radiusCustomer->is_active);
    }

    public function test_reactivated_event_listener_enables_radius_user(): void
    {
        $customer = Customer::factory()->create(['phone' => '01800000002']);
        $this->provisioningService->provisionUser($customer, null, ['radius_password' => 'secret']);
        $this->provisioningService->suspendUser($customer);

        $event = new SubscriptionReactivated($customer, null, 'Payment received');

        $listener = new HandleSubscriptionReactivated($this->provisioning());
        $listener->handle($event);

        $this->assertDatabaseMissing('radcheck', [
            'username' => '01800000002',
            'attribute' => 'Auth-Type',
            'value' => 'Reject',
        ], 'radius');

        $radiusCustomer = RadiusCustomer::where('customer_id', $customer->id)->first();
        $this->assertTrue($radiusCustomer->is_active);
    }

    public function test_terminated_event_listener_removes_radius_credentials(): void
    {
        $customer = Customer::factory()->create(['phone' => '01800000003']);
        $this->provisioningService->provisionUser($customer, null, ['radius_password' => 'secret']);

        $event = new SubscriptionTerminated($customer, 'Contract ended');

        $listener = new HandleSubscriptionTerminated($this->provisioning(), $this->coaService);
        $listener->handle($event);

        $this->assertDatabaseMissing('radcheck', ['username' => '01800000003'], 'radius');
        $this->assertDatabaseMissing('radreply', ['username' => '01800000003'], 'radius');
    }
}
