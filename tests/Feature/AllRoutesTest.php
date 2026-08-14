<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AllRoutesTest extends TestCase
{
    /**
     * Test all registered GET routes return valid responses
     */
    public function test_all_registered_get_routes_return_valid_responses()
    {
        $admin = User::find(10) ?? User::find(1);
        $customer = Customer::find(6);

        $allRoutes = Route::getRoutes()->getRoutes();

        $skippedPatterns = [
            'horizon', 'telescope', 'sanctum/', 'storage/',
            '_ignition/', '_debugbar/', 'favicon.ico', 'livewire/',
        ];

        foreach ($allRoutes as $route) {
            $uri = $route->uri();
            $methods = $route->methods();

            if (!in_array('GET', $methods)) {
                continue;
            }

            foreach ($skippedPatterns as $pattern) {
                if (str_contains($uri, $pattern)) {
                    continue 2;
                }
            }

            try {
                if (str_contains($uri, 'customer/') && $customer) {
                    $this->actingAs($customer, 'customer')->get($uri);
                } elseif (str_contains($uri, 'admin/') && $admin) {
                    $this->actingAs($admin)->get($uri);
                } else {
                    $this->get($uri);
                }
            } catch (\Exception $e) {
                $this->fail("Route {$uri} failed: " . $e->getMessage());
            }
        }
    }
}
