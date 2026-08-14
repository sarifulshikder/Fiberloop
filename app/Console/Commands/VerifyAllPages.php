<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

class VerifyAllPages extends Command
{
    protected $signature = 'app:verify-all-pages
        {--user=10 : User ID to authenticate as for admin routes}
        {--customer=6 : Customer ID for customer guard routes}
        {--output= : Output format (text, json, simple)}';

    protected $description = 'Verify all application pages load without errors';

    public function handle()
    {
        $outputFormat = $this->option('output') ?: 'text';

        if ($outputFormat === 'json') {
            $this->handleJson();
            return;
        }

        $this->handleText();
        return 0;
    }

    protected function handleText()
    {
        $admin = User::find($this->option('user')) ?? User::find(1);
        $customer = Customer::find($this->option('customer')) ?? Customer::find(6);

        if (!$admin) {
            $this->error('❌ Admin user not found. Use --user=ID to specify.');
            return 1;
        }

        if (!$customer) {
            $this->warn('⚠️  Customer not found. Customer-specific routes will be skipped.');
        }

        $this->info('🔍 Starting full application verification...');
        $this->line('');

        $total = 0;
        $passed = 0;
        $failed = [];

        // Test Admin Panel
        $adminResults = [];
        $this->testPanelRoutes('admin', $admin, $adminResults);
        $total += count($adminResults);
        foreach ($adminResults as $result) {
            if ($result['status'] === 'passed') {
                $passed++;
                $this->line("✓ {$result['uri']}");
            } else {
                $failed[] = "{$result['uri']} - {$result['error']}";
                $this->error("❌ {$result['uri']} - {$result['error']}");
            }
        }

        // Test Customer Panel
        if ($customer) {
            $customerResults = [];
            $this->testPanelRoutes('customer', $customer, $customerResults, 'customer');
            $total += count($customerResults);
            foreach ($customerResults as $result) {
                if ($result['status'] === 'passed') {
                    $passed++;
                    $this->line("✓ {$result['uri']}");
                } else {
                    $failed[] = "{$result['uri']} - {$result['error']}";
                    $this->error("❌ {$result['uri']} - {$result['error']}");
                }
            }
        }

        // Test API Routes
        $apiResults = [];
        $this->testApiRoutes($admin, $customer, $apiResults);
        $total += count($apiResults);
        foreach ($apiResults as $result) {
            if ($result['status'] === 'passed') {
                $passed++;
                $this->line("✓ {$result['uri']}");
            } else {
                $failed[] = "{$result['uri']} - {$result['error']}";
                $this->error("❌ {$result['uri']} - {$result['error']}");
            }
        }

        // Test Public Routes
        $publicResults = [];
        $this->testPublicRoutes($publicResults);
        $total += count($publicResults);
        foreach ($publicResults as $result) {
            if ($result['status'] === 'passed') {
                $passed++;
                $this->line("✓ {$result['uri']}");
            } else {
                $failed[] = "{$result['uri']} - {$result['error']}";
                $this->error("❌ {$result['uri']} - {$result['error']}");
            }
        }

        $this->line('');
        $this->line('═══════════════════════════════════════════════════════');
        $this->line("📊 Results: <fg=green>{$passed}</>={<$total}> passed");

        if (!empty($failed)) {
            $this->line('<fg=red>❌ ' . count($failed) . ' routes failed:</>');
            foreach ($failed as $failure) {
                $this->error('   - ' . $failure);
            }
            return 1;
        }

        $this->info('✅ All pages verified successfully!');
        return 0;
    }

    protected function handleJson()
    {
        $admin = User::find($this->option('user')) ?? User::find(1);
        $customer = Customer::find($this->option('customer')) ?? Customer::find(6);

        $results = [
            'admin' => [],
            'customer' => [],
            'api' => [],
            'public' => [],
        ];

        $this->testPanelRoutes('admin', $admin, $results['admin']);
        if ($customer) {
            $this->testPanelRoutes('customer', $customer, $results['customer'], 'customer');
        }
        $this->testApiRoutes($admin, $customer, $results['api']);
        $this->testPublicRoutes($results['public']);

        $this->output->writeln(json_encode($results, JSON_PRETTY_PRINT));
    }

    protected function testPanelRoutes($panel, $user, &$container, $guard = 'web')
    {
        if (is_array($container)) {
            $container = [];
        }

        $this->info("Testing {$panel} panel...");

        // Get all routes that contain the panel prefix
        $panelRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) use ($panel) {
                return str_contains($route->uri(), $panel . '/');
            });

        foreach ($panelRoutes as $route) {
            if (!in_array('GET', $route->methods()) && !in_array('HEAD', $route->methods())) {
                continue;
            }

            $uri = $route->uri();

            if (is_array($container)) {
                $container[] = $this->testSingleRoute($uri, $user, $guard);
            } else {
                $result = $this->testSingleRoute($uri, $user, $guard);
                if (isset($result['status']) && $result['status'] === 'passed') {
                    $container++;
                } elseif (isset($result['status']) && $result['status'] === 'failed') {
                    $this->error("❌ {$uri} - {$result['error']}");
                }
            }
        }
    }

    protected function testApiRoutes($admin, $customer, &$container)
    {
        if (is_array($container)) {
            $container = [];
        }

        $this->info('Testing API routes...');

        $apiRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(function ($route) {
                return str_starts_with($route->uri(), 'api/');
            });

        foreach ($apiRoutes as $route) {
            if (!in_array('GET', $route->methods())) {
                continue;
            }

            $uri = $route->uri();

            if (is_array($container)) {
                $container[] = $this->testSingleRoute($uri, $customer, 'customer');
            } else {
                $result = $this->testSingleRoute($uri, $customer, 'customer');
                if (isset($result['status']) && $result['status'] === 'passed') {
                    $container++;
                } elseif (isset($result['status']) && $result['status'] === 'failed') {
                    $this->error("❌ {$uri} - {$result['error']}");
                }
            }
        }
    }

    protected function testPublicRoutes(&$container)
    {
        if (is_array($container)) {
            $container = [];
        }

        $this->info('Testing public routes...');

        $publicRoutes = [
            '/',
            '/login',
            '/register',
            '/forgot-password',
            '/reset-password',
        ];

        foreach ($publicRoutes as $uri) {
            if (is_array($container)) {
                $container[] = $this->testSingleRoute($uri, null, null);
            } else {
                $result = $this->testSingleRoute($uri, null, null);
                if (isset($result['status']) && $result['status'] === 'passed') {
                    $container++;
                } elseif (isset($result['status']) && $result['status'] === 'failed') {
                    $this->error("❌ {$uri} - {$result['error']}");
                }
            }
        }
    }

    protected function testSingleRoute($uri, $user, $guard)
    {
        $url = url($uri);

        try {
            if ($user && $guard) {
                $token = auth($guard)->login($user);
                $response = Http::withHeaders([
                    'Accept' => str_contains($uri, 'api/') ? 'application/json' : 'text/html',
                    'Cookie' => "laravel_session={$token}",
                ])->get($url);
            } else {
                $response = Http::withHeaders([
                    'Accept' => str_contains($uri, 'api/') ? 'application/json' : 'text/html',
                ])->get($url);
            }

            $body = $response->body();
            $status = $response->status();

            $errorPatterns = [
                'Server Error',
                'Exception',
                'BadMethodCallException',
                'TypeError',
                'Undefined constant',
                'Call to undefined method',
                'Undefined property',
                'syntax error',
            ];

            foreach ($errorPatterns as $pattern) {
                if (str_contains($body, $pattern)) {
                    return [
                        'uri' => $uri,
                        'status' => 'failed',
                        'error' => 'Contains: ' . $pattern,
                        'http_status' => $status,
                    ];
                }
            }

            if ($status >= 500) {
                return [
                    'uri' => $uri,
                    'status' => 'failed',
                    'error' => 'HTTP ' . $status,
                    'http_status' => $status,
                ];
            }

            return [
                'uri' => $uri,
                'status' => 'passed',
                'http_status' => $status,
            ];

        } catch (\Exception $e) {
            return [
                'uri' => $uri,
                'status' => 'failed',
                'error' => 'Exception: ' . $e->getMessage(),
                'http_status' => 0,
            ];
        }
    }
}
