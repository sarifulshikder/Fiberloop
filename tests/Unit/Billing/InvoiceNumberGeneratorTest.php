<?php

use App\Models\InvoiceNumberSequence;
use App\Services\Billing\InvoiceNumberGenerator;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('generates sequential invoice numbers for a tenant', function () {
    $generator = new InvoiceNumberGenerator();
    $tenantId = 1;
    
    // Clean up any existing sequences for this tenant
    InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
    
    // Generate first invoice number
    $firstNumber = $generator->generateInvoiceNumber($tenantId);
    expect($firstNumber)->toBe('INV-1-00000001');
    
    // Generate second invoice number
    $secondNumber = $generator->generateInvoiceNumber($tenantId);
    expect($secondNumber)->toBe('INV-1-00000002');
    
    // Generate third invoice number
    $thirdNumber = $generator->generateInvoiceNumber($tenantId);
    expect($thirdNumber)->toBe('INV-1-00000003');
});

it('generates invoice numbers for different tenants independently', function () {
    $generator = new InvoiceNumberGenerator();
    $tenantId1 = 1;
    $tenantId2 = 2;
    
    // Clean up
    InvoiceNumberSequence::whereIn('tenant_id', [$tenantId1, $tenantId2])->delete();
    
    // Generate for tenant 1
    $number1a = $generator->generateInvoiceNumber($tenantId1);
    $number1b = $generator->generateInvoiceNumber($tenantId1);
    
    // Generate for tenant 2
    $number2a = $generator->generateInvoiceNumber($tenantId2);
    
    expect($number1a)->toBe('INV-1-00000001');
    expect($number1b)->toBe('INV-1-00000002');
    expect($number2a)->toBe('INV-2-00000001');
});

it('generates credit note numbers', function () {
    $generator = new InvoiceNumberGenerator();
    $tenantId = 1;
    
    InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
    
    $number = $generator->generateCreditNoteNumber($tenantId);
    expect($number)->toBe('CN-1-00000001');
});

it('generates refund numbers', function () {
    $generator = new InvoiceNumberGenerator();
    $tenantId = 1;
    
    InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
    
    $number = $generator->generateRefundNumber($tenantId);
    expect($number)->toBe('RFN-1-00000001');
});

it('gets current invoice number for tenant', function () {
    $generator = new InvoiceNumberGenerator();
    $tenantId = 1;
    
    InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
    
    // Generate a few numbers
    $generator->generateInvoiceNumber($tenantId);
    $generator->generateInvoiceNumber($tenantId);
    
    $current = $generator->getCurrentInvoiceNumber($tenantId);
    expect($current)->toBe(2);
});

it('creates sequence record if it does not exist', function () {
    $generator = new InvoiceNumberGenerator();
    $tenantId = 999; // New tenant
    
    InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
    
    $number = $generator->generateInvoiceNumber($tenantId);
    
    expect($number)->toBe('INV-999-00000001');
    
    // Verify sequence was created
    $sequence = InvoiceNumberSequence::where('tenant_id', $tenantId)->first();
    expect($sequence)->not->toBeNull();
    expect($sequence->last_invoice_number)->toBe(1);
});

it('resets invoice number sequence', function () {
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
    expect($current)->toBe(0);
    
    // Next number should start from 1
    $nextNumber = $generator->generateInvoiceNumber($tenantId);
    expect($nextNumber)->toBe('INV-1-00000001');
});

it('handles concurrent invoice number generation without gaps', function () {
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
    expect(count($uniqueNumbers))->toBe(10);
    
    // Verify sequence
    for ($i = 0; $i < 10; $i++) {
        $expectedSequence = $i + 1;
        expect($numbers[$i])->toBe(sprintf('INV-1-%08d', $expectedSequence));
    }
});

it('generates properly formatted numbers with correct padding', function () {
    $generator = new InvoiceNumberGenerator();
    $tenantId = 1;
    
    InvoiceNumberSequence::where('tenant_id', $tenantId)->delete();
    
    // Generate first number
    $number = $generator->generateInvoiceNumber($tenantId);
    
    // Should have 8-digit padding
    expect(strlen($number))->toBe(13); // "INV-1-" + 8 digits = 13
    expect(substr($number, -8))->toBe('00000001');
});

it('handles large sequence numbers', function () {
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
    expect($number)->toBe('INV-1-12345679');
});

it('throws exception for unknown number type', function () {
    $generator = new InvoiceNumberGenerator();
    
    expect(fn() => $generator->generateNumber(1, 'unknown'))
        ->toThrow(\InvalidArgumentException::class);
});
