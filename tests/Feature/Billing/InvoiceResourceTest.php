<?php

use App\Filament\Resources\InvoiceResource\Pages\CreateInvoice;
use App\Models\Customer;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    filament()->setCurrentPanel(filament()->getPanel('admin'));
});

it('renders the invoice create page for admins', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    $this->get(CreateInvoice::getUrl())
        ->assertSuccessful()
        ->assertSee('Invoice Number')
        ->assertSee('Customer');
});

it('keeps the invoice number field read-only on create', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    Livewire::test(CreateInvoice::class)
        ->assertFormFieldIsReadOnly('invoice_number');
});

it('auto-generates a sequential invoice number when creating an invoice', function () {
    $user = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($user);

    $customer = Customer::factory()->create();
    $package = Package::factory()->create(['price' => 100000]);
    $subscription = Subscription::factory()->forCustomer($customer)->forPackage($package)->create();

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'subtotal' => 100000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 100000,
            'paid_amount' => 0,
            'outstanding_amount' => 100000,
        ])
        ->call('create');

    $this->assertDatabaseHas('invoices', [
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-1-00000001',
    ]);

    Livewire::test(CreateInvoice::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'subscription_id' => $subscription->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'subtotal' => 100000,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 100000,
            'paid_amount' => 0,
            'outstanding_amount' => 100000,
        ])
        ->call('create');

    $this->assertDatabaseHas('invoices', [
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-1-00000002',
    ]);
});
