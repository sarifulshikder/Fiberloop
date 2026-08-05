<?php

namespace Tests\Unit\Billing;

use App\Models\InvoiceNumberSequence;
use App\Services\Billing\InvoiceNumberGenerator;
use Tests\TestCase;

class InvoiceNumberGeneratorTestPHPUnit extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clean up before each test
        InvoiceNumberSequence::where('tenant_id', 1)->delete();
        InvoiceNumberSequence::where('tenant_id', 2)->delete();
        InvoiceNumberSequence::where('tenant_id', 999)->delete();
    }

    public function test_generates_sequential_invoice_numbers_for_a_tenant()
    {
        $generator = new InvoiceNumberGenerator();
        $tenantId = 1;
        
        // Generate first invoice number
        $firstNumber = $generator->generateInvoiceNumber($tenantId);
        $this->assertEquals('INV-1-00000001', $firstNumber);
        
        // Generate second invoice number
        $secondNumber = $generator->generateInvoiceNumber($tenantId);
        $this->assertEquals('INV-1-00000002', $secondNumber);
        
        // Generate third invoice number
        $thirdNumber = $generator->generateInvoiceNumber($tenantId);
        $this->assertEquals('INV-1-00000003', $thirdNumber);
    }

    public function test_generates_invoice_numbers_for_different_tenants_independently()
    {
        $generator = new InvoiceNumberGenerator();
        $tenantId1 = 1;
        $tenantId2 = 2;
        
        // Generate for tenant 1
        $number1a = $generator->generateInvoiceNumber($tenantId1);
        $number1b = $generator->generateInvoiceNumber($tenantId1);
        
        // Generate for tenant 2
        $number2a = $generator->generateInvoiceNumber($tenantId2);
        
        $this->assertEquals('INV-1-00000001', $number1a);
        $this->assertEquals('INV-1-00000002', $number1b);
        $this->assertEquals('INV-2-00000001', $number2a);
    }

    public function test_generates_credit_note_numbers()
    {
        $generator = new InvoiceNumberGenerator();
        $tenantId = 1;
        
        InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
        
        $number = $generator->generateCreditNoteNumber($tenantId);
        $this->assertEquals('CN-1-00000001', $number);
    }

    public function test_generates_refund_numbers()
    {
        $generator = new InvoiceNumberGenerator();
        $tenantId = 1;
        
        InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
        
        $number = $generator->generateRefundNumber($tenantId);
        $this->assertEquals('RFN-1-00000001', $number);
    }

    public function test_gets_current_invoice_number_for_tenant()
    {
        $generator = new InvoiceNumberGenerator();
        $tenantId = 1;
        
        InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
        
        // Generate a few numbers
        $generator->generateInvoiceNumber($tenantId);
        $generator->generateInvoiceNumber($tenantId);
        
        $current = $generator->getCurrentInvoiceNumber($tenantId);
        $this->assertEquals(2, $current);
    }

    public function test_creates_sequence_record_if_it_does_not_exist()
    {
        $generator = new InvoiceNumberGenerator();
        $tenantId = 999; // New tenant
        
        InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
        
        $number = $generator->generateInvoiceNumber($tenantId);
        
        $this->assertEquals('INV-999-00000001', $number);
        
        // Verify sequence was created
        $sequence = InvoiceNumberSequence::where('tenant_id', $tenantId)->first();
        $this->assertNotNull($sequence);
        $this->assertEquals(1, $sequence->last_invoice_number);
    }

    public function test_resets_invoice_number_sequence()
    {
        $generator = new InvoiceNumberGenerator();
        $tenantId = 1;
        
        InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
        
        // Generate some numbers
        $generator->generateInvoiceNumber($tenantId);
        $generator->generateInvoiceNumber($tenantId);
        $generator->generateInvoiceNumber($tenantId);
        
        // Reset to 0
        $generator->resetInvoiceNumber($tenantId, 0);
        
        $current = $generator->getCurrentInvoiceNumber($tenantId);
        $this->assertEquals(0, $current);
        
        // Next number should start from 1
        $nextNumber = $generator->generateInvoiceNumber($tenantId);
        $this->assertEquals('INV-1-00000001', $nextNumber);
    }

    public function test_handles_concurrent_invoice_number_generation_without_gaps()
    {
        $generator = new InvoiceNumberGenerator();
        $tenantId = 1;
        
        InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
        
        // Generate multiple numbers
        $numbers = [];
        for ($i = 1; $i <= 10; $i++) {
            $numbers[] = $generator->generateInvoiceNumber($tenantId);
        }
        
        // Verify all numbers are unique and sequential
        $uniqueNumbers = array_unique($numbers);
        $this->assertCount(10, $uniqueNumbers);
        
        // Verify sequence
        for ($i = 0; $i < 10; $i++) {
            $expectedSequence = $i + 1;
            $this->assertEquals(sprintf('INV-1-%08d', $expectedSequence), $numbers[$i]);
        }
    }

    public function test_generates_properly_formatted_numbers_with_correct_padding()
    {
        $generator = new InvoiceNumberGenerator();
        $tenantId = 1;
        
        InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
        
        // Generate first number
        $number = $generator->generateInvoiceNumber($tenantId);
        
        // Should have 8-digit padding
        $this->assertEquals(14, strlen($number)); // "INV-1-" + 8 digits = 14
        $this->assertEquals('00000001', substr($number, -8));
    }

    public function test_handles_large_sequence_numbers()
    {
        $generator = new InvoiceNumberGenerator();
        $tenantId = 1;
        
        InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
        
        // Set a large sequence number
        InvoiceNumberSequence::create([
            'tenant_id' => $tenantId,
            'last_invoice_number' => 12345678,
            'last_credit_note_number' => 0,
            'last_refund_number' => 0,
        ]);
        
        $number = $generator->generateInvoiceNumber($tenantId);
        $this->assertEquals('INV-1-12345679', $number);
    }
}
