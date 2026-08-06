<?php

namespace Tests\Feature\Inventory;

use App\Enums\InventoryStatus;
use App\Models\Customer;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryItemTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
        ]);

        $this->customer = Customer::factory()->create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'phone' => '01700000000',
            'email' => 'customer@test.com',
        ]);
    }

    /** @test */
    public function it_can_create_an_inventory_item()
    {
        $item = InventoryItem::factory()->create([
            'name' => 'Test ONT',
            'item_type' => 'ONT',
            'brand' => 'Huawei',
            'model' => 'HG8245H',
            'serial_number' => 'SN123456789',
            'mac_address' => '00:11:22:33:44:55',
            'purchase_price' => 500000, // BDT 5000 in poysha
            'selling_price' => 600000, // BDT 6000 in poysha
            'status' => InventoryStatus::IN_STOCK,
            'warehouse' => 'Main Warehouse',
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'name' => 'Test ONT',
            'serial_number' => 'SN123456789',
        ]);
    }

    /** @test */
    public function it_can_assign_item_to_customer()
    {
        $item = InventoryItem::factory()->create([
            'name' => 'Test ONT',
            'status' => InventoryStatus::IN_STOCK,
        ]);

        $item->update([
            'customer_id' => $this->customer->id,
            'status' => InventoryStatus::ASSIGNED,
            'assigned_at' => now(),
            'assigned_location' => $this->customer->service_address,
        ]);

        $this->assertEquals(InventoryStatus::ASSIGNED, $item->fresh()->status);
        $this->assertEquals($this->customer->id, $item->fresh()->customer_id);
    }

    /** @test */
    public function it_can_flag_item_for_return_on_termination()
    {
        $item = InventoryItem::factory()->create([
            'name' => 'Test ONT',
            'customer_id' => $this->customer->id,
            'status' => InventoryStatus::ASSIGNED,
        ]);

        $item->update([
            'status' => InventoryStatus::NEEDS_INSPECTION,
            'returned_at' => now(),
        ]);

        $this->assertEquals(InventoryStatus::NEEDS_INSPECTION, $item->fresh()->status);
    }

    /** @test */
    public function it_can_retire_item()
    {
        $item = InventoryItem::factory()->create([
            'name' => 'Old Router',
            'status' => InventoryStatus::IN_STOCK,
        ]);

        $item->update([
            'status' => InventoryStatus::RETIRED,
        ]);

        $this->assertEquals(InventoryStatus::RETIRED, $item->fresh()->status);
    }

    /** @test */
    public function it_scopes_in_stock_items()
    {
        InventoryItem::factory()->create(['status' => InventoryStatus::IN_STOCK]);
        InventoryItem::factory()->create(['status' => InventoryStatus::ASSIGNED]);
        InventoryItem::factory()->create(['status' => InventoryStatus::IN_STOCK]);

        $inStock = InventoryItem::inStock()->count();

        $this->assertEquals(2, $inStock);
    }

    /** @test */
    public function it_scopes_assigned_items()
    {
        InventoryItem::factory()->create(['status' => InventoryStatus::IN_STOCK]);
        InventoryItem::factory()->create(['status' => InventoryStatus::ASSIGNED]);
        InventoryItem::factory()->create(['status' => InventoryStatus::ASSIGNED]);

        $assigned = InventoryItem::assigned()->count();

        $this->assertEquals(2, $assigned);
    }

    /** @test */
    public function it_scopes_by_tenant()
    {
        $tenantId = 1;
        InventoryItem::factory()->create(['tenant_id' => $tenantId]);
        InventoryItem::factory()->create(['tenant_id' => 2]);
        InventoryItem::factory()->create(['tenant_id' => $tenantId]);

        $tenantItems = InventoryItem::byTenant($tenantId)->count();

        $this->assertEquals(2, $tenantItems);
    }

    /** @test */
    public function it_scopes_by_type()
    {
        InventoryItem::factory()->create(['item_type' => 'ONT']);
        InventoryItem::factory()->create(['item_type' => 'router']);
        InventoryItem::factory()->create(['item_type' => 'ONT']);

        $ontItems = InventoryItem::byType('ONT')->count();

        $this->assertEquals(2, $ontItems);
    }

    /** @test */
    public function it_detects_warranty_expiring()
    {
        // Item with warranty expiring in 20 days
        $expiringSoon = InventoryItem::factory()->create([
            'warranty_end' => now()->addDays(20),
        ]);

        // Item with warranty expiring in 40 days
        $notExpiring = InventoryItem::factory()->create([
            'warranty_end' => now()->addDays(40),
        ]);

        $expiringItems = InventoryItem::warrantyExpiring(30)->get();

        $this->assertTrue($expiringItems->contains($expiringSoon));
        $this->assertFalse($expiringItems->contains($notExpiring));
    }

    /** @test */
    public function it_converts_prices_correctly()
    {
        $item = InventoryItem::factory()->create([
            'purchase_price' => 500000, // 5000 BDT in poysha
            'selling_price' => 600000, // 6000 BDT in poysha
        ]);

        $this->assertEquals(500000, $item->purchase_price);
        $this->assertEquals(600000, $item->selling_price);
    }

    /** @test */
    public function it_has_correct_fillable_fields()
    {
        $fillable = (new InventoryItem())->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('item_type', $fillable);
        $this->assertContains('serial_number', $fillable);
        $this->assertContains('mac_address', $fillable);
        $this->assertContains('status', $fillable);
    }

    /** @test */
    public function it_has_correct_casts()
    {
        $casts = (new InventoryItem())->getCasts();

        $this->assertArrayHasKey('status', $casts);
        $this->assertArrayHasKey('purchase_price', $casts);
        $this->assertArrayHasKey('selling_price', $casts);
        $this->assertEquals('integer', $casts['purchase_price']);
        $this->assertEquals('integer', $casts['selling_price']);
    }
}
