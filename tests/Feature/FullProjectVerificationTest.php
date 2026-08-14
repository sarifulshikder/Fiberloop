<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Tests\TestCase;

class FullProjectVerificationTest extends TestCase
{
    /**
     * Test all admin panel pages load without errors
     */
    public function test_all_admin_panel_pages_load_without_errors()
    {
        $admin = User::where('email', 'admin@fiberloop.com')->first()
            ?? User::where('email', 'superadmin@fiberloop.com')->first()
            ?? User::find(10);

        if (!$admin) {
            $this->markTestSkipped('No admin user found');
            return;
        }

        $adminRoutes = [
            'filament.admin.pages.dashboard',
            'filament.admin.resources.customers.index',
            'filament.admin.resources.invoices.index',
            'filament.admin.resources.payments.index',
            'filament.admin.resources.tickets.index',
        ];

        foreach ($adminRoutes as $route) {
            if (route($route, [], false)) {
                $response = $this->actingAs($admin)->get(route($route));
                $response->assertOk()
                         ->assertDontSee(['Server Error', 'Exception', 'BadMethodCallException', 'TypeError', 'Undefined', 'Call to undefined method']);
            }
        }
    }

    /**
     * Test all customer panel pages load without errors
     */
    public function test_all_customer_panel_pages_load_without_errors()
    {
        $customer = Customer::find(6);

        if (!$customer) {
            $this->markTestSkipped('No customer with ID 6 found');
            return;
        }

        $customerRoutes = [
            'filament.customer.pages.customer-dashboard',
            'filament.customer.resources.profiles.index',
            'filament.customer.resources.invoices.index',
            'filament.customer.resources.payments.index',
            'filament.customer.resources.subscriptions.index',
            'filament.customer.resources.tickets.index',
        ];

        foreach ($customerRoutes as $route) {
            if (route($route, [], false)) {
                $response = $this->actingAs($customer, 'customer')->get(route($route));
                $response->assertOk()
                         ->assertDontSee(['Server Error', 'Exception', 'BadMethodCallException', 'TypeError', 'Undefined', 'Call to undefined method']);
            }
        }
    }

    /**
     * Test public routes load without errors
     */
    public function test_public_routes_load_without_errors()
    {
        $response = $this->get('/');
        $response->assertOk()
                 ->assertDontSee(['Server Error', 'Exception']);
    }
}
