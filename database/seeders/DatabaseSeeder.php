<?php

namespace Database\Seeders;

use App\Enums\BillingType;
use App\Enums\ConnectionType;
use App\Enums\CustomerStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PackageBillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ResellerStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InventoryItem;
use App\Models\NetworkDevice;
use App\Models\NotificationLog;
use App\Models\Onu;
use App\Models\Olt;
use App\Models\Package;
use App\Models\Payment;
use App\Models\RadiusCustomer;
use App\Models\Reseller;
use App\Models\Subscription;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(RolesAndPermissionsSeeder::class);

        // Create admin user
        $adminUser = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@fiberloop.com',
            'password' => Hash::make('password'),
            'phone' => '+8801700000000',
            'is_super_admin' => true,
            'is_active' => true,
        ]);

        // Create billing agent
        $billingAgent = User::factory()->create([
            'name' => 'Billing Agent',
            'email' => 'billing@fiberloop.com',
            'password' => Hash::make('password'),
            'phone' => '+8801700000001',
            'is_super_admin' => false,
            'is_active' => true,
        ]);

        // Create NOC engineer
        $nocEngineer = User::factory()->create([
            'name' => 'NOC Engineer',
            'email' => 'noc@fiberloop.com',
            'password' => Hash::make('password'),
            'phone' => '+8801700000002',
            'is_super_admin' => false,
            'is_active' => true,
        ]);

        echo "Created users\n";

        // Create packages
        $packages = Package::factory()->count(10)->create();
        echo "Created {$packages->count()} packages\n";

        // Create resellers
        $resellers = Reseller::factory()->count(5)->create();
        foreach ($resellers as $reseller) {
            Reseller::factory()->count(rand(0, 2))->withParent($reseller)->create();
        }
        echo "Created resellers\n";

        // Create customers (500+)
        $customers = Customer::factory()->count(500)->create();
        echo "Created {$customers->count()} customers\n";

        // Create network devices
        $networkDevices = NetworkDevice::factory()->count(10)->create();
        echo "Created {$networkDevices->count()} network devices\n";

        // Create OLTs
        $olts = Olt::factory()->count(5)->create();
        echo "Created {$olts->count()} OLTs\n";

        // Create subscriptions for customers
        $subscriptions = [];
        foreach ($customers as $customer) {
            $package = $packages->random();
            $subscription = Subscription::factory()->forCustomer($customer)->create([
                'package_id' => $package->id,
                'monthly_price' => $package->price,
                'final_price' => $package->price,
                'status' => fake()->randomElement([
                    SubscriptionStatus::ACTIVE,
                    SubscriptionStatus::ACTIVE,
                    SubscriptionStatus::ACTIVE,
                    SubscriptionStatus::SUSPENDED,
                    SubscriptionStatus::EXPIRED,
                ]),
            ]);
            $subscriptions[] = $subscription;
            
            // Create ONU for some customers
            if (rand(0, 1) == 1 && $olts->count() > 0) {
                Onu::factory()->forCustomer($customer)->forOlt($olts->random())->create();
            }
            
            // Create RADIUS customer
            RadiusCustomer::factory()->forCustomer($customer)->create();
        }
        echo "Created " . count($subscriptions) . " subscriptions\n";

        // Create invoices for active subscriptions
        foreach ($subscriptions as $subscription) {
            if ($subscription->status === SubscriptionStatus::ACTIVE) {
                $invoiceCount = rand(1, 12); // 1-12 months of invoices
                for ($i = 0; $i < $invoiceCount; $i++) {
                    $invoice = Invoice::factory()->forCustomer($subscription->customer)->forSubscription($subscription)->create([
                        'due_date' => fake()->dateTimeBetween('-6 months', '+1 month'),
                        'status' => fake()->randomElement([
                            InvoiceStatus::DRAFT,
                            InvoiceStatus::SENT,
                            InvoiceStatus::SENT,
                            InvoiceStatus::PAID,
                            InvoiceStatus::OVERDUE,
                            InvoiceStatus::OVERDUE,
                        ]),
                    ]);
                    
                    // Create invoice items
                    InvoiceItem::factory()->count(rand(1, 3))->forInvoice($invoice)->create();
                    
                    // Create payments for some invoices
                    if ($invoice->status === InvoiceStatus::PAID || (rand(0, 1) == 1 && $invoice->status === InvoiceStatus::SENT)) {
                        Payment::factory()->forInvoice($invoice)->forCustomer($subscription->customer)->create([
                            'amount' => $invoice->total,
                            'net_amount' => $invoice->total,
                            'status' => PaymentStatus::COMPLETED,
                            'method' => fake()->randomElement([
                                PaymentMethod::BKASH,
                                PaymentMethod::NAGAD,
                                PaymentMethod::CASH,
                                PaymentMethod::BANK,
                            ]),
                            'paid_at' => now(),
                        ]);
                    }
                }
            }
        }
        echo "Created invoices and payments\n";

        // Create tickets
        $tickets = Ticket::factory()->count(100)->create();
        echo "Created {$tickets->count()} tickets\n";

        // Create inventory items
        $inventoryItems = InventoryItem::factory()->count(200)->create();
        echo "Created {$inventoryItems->count()} inventory items\n";

        // Create notification logs
        NotificationLog::factory()->count(200)->create();
        echo "Created notification logs\n";

        // Update some invoices to overdue for testing
        Invoice::where('status', InvoiceStatus::SENT)
            ->where('due_date', '<', now()->subDays(7))
            ->update(['status' => InvoiceStatus::OVERDUE]);

        echo "Database seeding complete!\n";
    }
}