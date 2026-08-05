<?php

namespace App\Services\Billing;

use App\Models\InvoiceNumberSequence;
use Illuminate\Support\Facades\DB;

/**
 * Generates sequential, gapless invoice numbers per tenant.
 * Uses database transactions with row locking to prevent race conditions.
 */
class InvoiceNumberGenerator
{
    /**
     * Generate the next invoice number for a tenant.
     * Format: INV-{tenant_id}-{sequence}
     */
    public function generateInvoiceNumber(int $tenantId): string
    {
        return $this->generateNumber($tenantId, 'invoice');
    }

    /**
     * Generate the next credit note number for a tenant.
     * Format: CN-{tenant_id}-{sequence}
     */
    public function generateCreditNoteNumber(int $tenantId): string
    {
        return $this->generateNumber($tenantId, 'credit_note');
    }

    /**
     * Generate the next refund number for a tenant.
     * Format: RFN-{tenant_id}-{sequence}
     */
    public function generateRefundNumber(int $tenantId): string
    {
        return $this->generateNumber($tenantId, 'refund');
    }

    /**
     * Generate a sequential number with locking to prevent race conditions.
     */
    protected function generateNumber(int $tenantId, string $type): string
    {
        return DB::transaction(function () use ($tenantId, $type) {
            $sequence = InvoiceNumberSequence::forTenant($tenantId)
                ->lockForUpdate()
                ->firstOrCreate([
                    'tenant_id' => $tenantId,
                ], [
                    'last_invoice_number' => 0,
                    'last_credit_note_number' => 0,
                    'last_refund_number' => 0,
                ]);

            $number = match ($type) {
                'invoice' => ++$sequence->last_invoice_number,
                'credit_note' => ++$sequence->last_credit_note_number,
                'refund' => ++$sequence->last_refund_number,
                default => throw new \InvalidArgumentException("Unknown number type: {$type}"),
            };

            $sequence->save();

            return $this->formatNumber($type, $tenantId, $number);
        });
    }

    /**
     * Format the number with prefix and padding.
     */
    protected function formatNumber(string $type, int $tenantId, int $number): string
    {
        $prefix = match ($type) {
            'invoice' => 'INV',
            'credit_note' => 'CN',
            'refund' => 'RFN',
            default => throw new \InvalidArgumentException("Unknown number type: {$type}"),
        };

        return sprintf('%s-%d-%08d', $prefix, $tenantId, $number);
    }

    /**
     * Get the current invoice number for a tenant (for display/debugging).
     */
    public function getCurrentInvoiceNumber(int $tenantId): int
    {
        $sequence = InvoiceNumberSequence::forTenant($tenantId)->first();
        return $sequence ? $sequence->last_invoice_number : 0;
    }

    /**
     * Reset the invoice number sequence for a tenant (use with caution).
     */
    public function resetInvoiceNumber(int $tenantId, int $newNumber = 0): void
    {
        DB::transaction(function () use ($tenantId, $newNumber) {
            InvoiceNumberSequence::forTenant($tenantId)
                ->lockForUpdate()
                ->update(['last_invoice_number' => $newNumber]);
        });
    }
}
