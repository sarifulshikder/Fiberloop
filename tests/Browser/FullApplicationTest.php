<?php

namespace Tests\Browser;

use App\Models\Customer;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class FullApplicationTest extends DuskTestCase
{
    /**
     * Test admin panel navigation
     */
    public function test_admin_panel_navigation()
    {
        $admin = User::find(10) ?? User::find(1) ?? User::factory()->create(['password' => bcrypt('password')]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin)
                   ->visit('/admin')
                   ->assertSee('Dashboard')
                   ->assertMissing('Server Error')
                   ->assertMissing('Exception')
                   ->assertMissing('BadMethodCallException')
                   ->assertMissing('TypeError')
                   ->assertMissing('Undefined');

            // Test navigation to all admin resources
            $navItems = [
                'Dashboard',
                'Customers',
                'Packages',
                'Invoices',
                'Payments',
                'Tickets',
                'Subscriptions',
                'Users',
                'Roles',
                'Permissions',
                'Leads',
                'Resellers',
                'Settings',
            ];

            foreach ($navItems as $item) {
                // Try to find and click the navigation item
                $selector = ".filament-navigation-item:contains('{$item}')";
                if ($browser->element($selector)) {
                    $browser->click($selector)
                           ->pause(500)
                           ->assertMissing('Server Error')
                           ->assertMissing('Exception')
                           ->assertMissing('BadMethodCallException');
                    $browser->back();
                    $browser->pause(300);
                }
            }
        });
    }

    /**
     * Test customer panel navigation
     */
    public function test_customer_panel_navigation()
    {
        $customer = Customer::find(6);

        if (!$customer) {
            $this->markTestSkipped('No customer with ID 6 found');
            return;
        }

        $this->browse(function (Browser $browser) use ($customer) {
            $browser->loginAs($customer, 'customer')
                   ->visit('/customer')
                   ->assertSee('Dashboard')
                   ->assertMissing('Server Error')
                   ->assertMissing('Exception')
                   ->assertMissing('BadMethodCallException')
                   ->assertMissing('TypeError')
                   ->assertMissing('Undefined');

            // Test all customer resources
            $resources = [
                'profiles',
                'invoices',
                'payments',
                'subscriptions',
                'tickets',
            ];

            foreach ($resources as $resource) {
                $browser->visit("/customer/{$resource}")
                       ->pause(500)
                       ->assertMissing('Server Error')
                       ->assertMissing('Exception')
                       ->assertMissing('BadMethodCallException');
            }
        });
    }

    /**
     * Test public pages
     */
    public function test_public_pages()
    {
        $this->browse(function (Browser $browser) {
            $pages = [
                '/',
                '/login',
                '/register',
                '/forgot-password',
            ];

            foreach ($pages as $page) {
                $browser->visit($page)
                       ->pause(500)
                       ->assertMissing('Server Error')
                       ->assertMissing('Exception')
                       ->assertMissing('BadMethodCallException');
            }
        });
    }

    /**
     * Test admin panel resource pages
     */
    public function test_admin_resource_pages()
    {
        $admin = User::find(10) ?? User::find(1) ?? User::factory()->create(['password' => bcrypt('password')]);

        $this->browse(function (Browser $browser) use ($admin) {
            $browser->loginAs($admin);

            // Test resource list pages
            $resourceRoutes = [
                '/admin/customers',
                '/admin/packages',
                '/admin/invoices',
                '/admin/payments',
                '/admin/tickets',
                '/admin/subscriptions',
                '/admin/users',
                '/admin/roles',
                '/admin/permissions',
            ];

            foreach ($resourceRoutes as $route) {
                $browser->visit($route)
                       ->pause(500)
                       ->assertMissing('Server Error')
                       ->assertMissing('Exception');
            }
        });
    }

    /**
     * Test customer panel resource pages
     */
    public function test_customer_resource_pages()
    {
        $customer = Customer::find(6);

        if (!$customer) {
            $this->markTestSkipped('No customer with ID 6 found');
            return;
        }

        $this->browse(function (Browser $browser) use ($customer) {
            $browser->loginAs($customer, 'customer');

            $resourceRoutes = [
                '/customer/profiles',
                '/customer/invoices',
                '/customer/payments',
                '/customer/subscriptions',
                '/customer/tickets',
            ];

            foreach ($resourceRoutes as $route) {
                $browser->visit($route)
                       ->pause(500)
                       ->assertMissing('Server Error')
                       ->assertMissing('Exception')
                       ->assertMissing('BadMethodCallException');
            }
        });
    }
}
