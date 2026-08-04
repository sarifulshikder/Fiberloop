<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT check_invoices_subtotal_non_negative CHECK (subtotal >= 0)");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT check_invoices_tax_amount_non_negative CHECK (tax_amount >= 0)");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT check_invoices_discount_amount_non_negative CHECK (discount_amount >= 0)");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT check_invoices_total_non_negative CHECK (total >= 0)");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT check_invoices_paid_amount_non_negative CHECK (paid_amount >= 0)");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT check_invoices_outstanding_amount_non_negative CHECK (outstanding_amount >= 0)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE invoices DROP CONSTRAINT IF EXISTS check_invoices_subtotal_non_negative");
        DB::statement("ALTER TABLE invoices DROP CONSTRAINT IF EXISTS check_invoices_tax_amount_non_negative");
        DB::statement("ALTER TABLE invoices DROP CONSTRAINT IF EXISTS check_invoices_discount_amount_non_negative");
        DB::statement("ALTER TABLE invoices DROP CONSTRAINT IF EXISTS check_invoices_total_non_negative");
        DB::statement("ALTER TABLE invoices DROP CONSTRAINT IF EXISTS check_invoices_paid_amount_non_negative");
        DB::statement("ALTER TABLE invoices DROP CONSTRAINT IF EXISTS check_invoices_outstanding_amount_non_negative");
    }
};