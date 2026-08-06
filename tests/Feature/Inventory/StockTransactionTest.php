<?php

namespace Tests\Feature\Inventory;

use App\Enums\InventoryStatus;
use App\Enums\StockTransactionReason;
use App\Enums\StockTransactionType;
use App\Models\InventoryItem;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
        ]);
    }

    /** @test */
    public function it_can_create_a_stock_receipt_transaction()
    {
        $item = InventoryItem::factory()->create([
            'name' => 'Test ONT',
            'status' => InventoryStatus::IN_STOCK,
        ]);

        $transaction = StockTransaction::factory()->create([
            'inventory_item_id' => $item->id,
            'transaction_type' => StockTransactionType::RECEIPT,
            'reason' => StockTransactionReason::PURCHASE,
            'quantity' => 1,
            'unit_cost' => 500000,
            'total_cost' => 500000,
            'previous_status' => null,
            'new_status' => InventoryStatus::IN_STOCK,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('stock_transactions', [
            'id' => $transaction->id,
            'transaction_type' => StockTransactionType::RECEIPT->value,
            'reason' => StockTransactionReason::PURCHASE->value,
        ]);
    }

    /** @test */
    public function it_can_create_an_issue_transaction()
    {
        $item = InventoryItem::factory()->create([
            'name' => 'Test ONT',
            'status' => InventoryStatus::IN_STOCK,
        ]);

        $transaction = StockTransaction::factory()->create([
            'inventory_item_id' => $item->id,
            'transaction_type' => StockTransactionType::ISSUE,
            'reason' => StockTransactionReason::NEW_INSTALLATION,
            'quantity' => 1,
            'previous_status' => InventoryStatus::IN_STOCK,
            'new_status' => InventoryStatus::ASSIGNED,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $this->assertEquals(StockTransactionType::ISSUE->value, $transaction->transaction_type->value);
        $this->assertEquals(StockTransactionReason::NEW_INSTALLATION->value, $transaction->reason->value);
    }

    /** @test */
    public function it_can_create_a_return_transaction()
    {
        $item = InventoryItem::factory()->create([
            'name' => 'Test ONT',
            'status' => InventoryStatus::ASSIGNED,
        ]);

        $transaction = StockTransaction::factory()->create([
            'inventory_item_id' => $item->id,
            'transaction_type' => StockTransactionType::RETURN,
            'reason' => StockTransactionReason::CUSTOMER_TERMINATION,
            'quantity' => 1,
            'previous_status' => InventoryStatus::ASSIGNED,
            'new_status' => InventoryStatus::NEEDS_INSPECTION,
            'user_id' => $this->admin->id,
            'created_by' => $this->admin->id,
        ]);

        $this->assertTrue($transaction->isIncoming());
        $this->assertFalse($transaction->isOutgoing());
    }

    /** @test */
    public function it_identifies_incoming_transactions()
    {
        $receipt = StockTransaction::factory()->create([
            'transaction_type' => StockTransactionType::RECEIPT,
        ]);

        $return = StockTransaction::factory()->create([
            'transaction_type' => StockTransactionType::RETURN,
        ]);

        $adjustment = StockTransaction::factory()->create([
            'transaction_type' => StockTransactionType::ADJUSTMENT,
        ]);

        $issue = StockTransaction::factory()->create([
            'transaction_type' => StockTransactionType::ISSUE,
        ]);

        $this->assertTrue($receipt->isIncoming());
        $this->assertTrue($return->isIncoming());
        $this->assertTrue($adjustment->isIncoming());
        $this->assertFalse($issue->isIncoming());
    }

    /** @test */
    public function it_identifies_outgoing_transactions()
    {
        $issue = StockTransaction::factory()->create([
            'transaction_type' => StockTransactionType::ISSUE,
        ]);

        $transfer = StockTransaction::factory()->create([
            'transaction_type' => StockTransactionType::TRANSFER,
        ]);

        $retirement = StockTransaction::factory()->create([
            'transaction_type' => StockTransactionType::RETIREMENT,
        ]);

        $disposal = StockTransaction::factory()->create([
            'transaction_type' => StockTransactionType::DISPOSAL,
        ]);

        $this->assertTrue($issue->isOutgoing());
        $this->assertTrue($transfer->isOutgoing());
        $this->assertTrue($retirement->isOutgoing());
        $this->assertTrue($disposal->isOutgoing());
    }

    /** @test */
    public function it_scopes_incoming_transactions()
    {
        StockTransaction::factory()->create(['transaction_type' => StockTransactionType::RECEIPT]);
        StockTransaction::factory()->create(['transaction_type' => StockTransactionType::ISSUE]);
        StockTransaction::factory()->create(['transaction_type' => StockTransactionType::RETURN]);
        StockTransaction::factory()->create(['transaction_type' => StockTransactionType::TRANSFER]);

        $incoming = StockTransaction::incoming()->count();

        $this->assertEquals(2, $incoming);
    }

    /** @test */
    public function it_scopes_outgoing_transactions()
    {
        StockTransaction::factory()->create(['transaction_type' => StockTransactionType::RECEIPT]);
        StockTransaction::factory()->create(['transaction_type' => StockTransactionType::ISSUE]);
        StockTransaction::factory()->create(['transaction_type' => StockTransactionType::RETURN]);
        StockTransaction::factory()->create(['transaction_type' => StockTransactionType::TRANSFER]);

        $outgoing = StockTransaction::outgoing()->count();

        $this->assertEquals(2, $outgoing);
    }

    /** @test */
    public function it_scopes_by_transaction_type()
    {
        StockTransaction::factory()->create(['transaction_type' => StockTransactionType::RECEIPT]);
        StockTransaction::factory()->create(['transaction_type' => StockTransactionType::ISSUE]);
        StockTransaction::factory()->create(['transaction_type' => StockTransactionType::RECEIPT]);

        $receipts = StockTransaction::byType(StockTransactionType::RECEIPT)->count();

        $this->assertEquals(2, $receipts);
    }

    /** @test */
    public function it_scopes_by_inventory_item()
    {
        $item1 = InventoryItem::factory()->create();
        $item2 = InventoryItem::factory()->create();

        StockTransaction::factory()->create(['inventory_item_id' => $item1->id]);
        StockTransaction::factory()->create(['inventory_item_id' => $item1->id]);
        StockTransaction::factory()->create(['inventory_item_id' => $item2->id]);

        $item1Transactions = StockTransaction::byItem($item1->id)->count();

        $this->assertEquals(2, $item1Transactions);
    }

    /** @test */
    public function it_has_correct_fillable_fields()
    {
        $fillable = (new StockTransaction())->getFillable();

        $this->assertContains('inventory_item_id', $fillable);
        $this->assertContains('transaction_type', $fillable);
        $this->assertContains('reason', $fillable);
        $this->assertContains('reference_number', $fillable);
        $this->assertContains('quantity', $fillable);
    }

    /** @test */
    public function it_has_correct_casts()
    {
        $casts = (new StockTransaction())->getCasts();

        $this->assertArrayHasKey('transaction_type', $casts);
        $this->assertArrayHasKey('reason', $casts);
        $this->assertArrayHasKey('quantity', $casts);
        $this->assertEquals('integer', $casts['quantity']);
    }
}
