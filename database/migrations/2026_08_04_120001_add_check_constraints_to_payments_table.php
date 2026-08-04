<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payments ADD CONSTRAINT check_payments_amount_non_negative CHECK (amount >= 0)");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT check_payments_fee_amount_non_negative CHECK (fee_amount >= 0)");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT check_payments_net_amount_non_negative CHECK (net_amount >= 0)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS check_payments_amount_non_negative");
        DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS check_payments_fee_amount_non_negative");
        DB::statement("ALTER TABLE payments DROP CONSTRAINT IF EXISTS check_payments_net_amount_non_negative");
    }
};