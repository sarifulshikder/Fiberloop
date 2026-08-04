<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT check_subscriptions_monthly_price_non_negative CHECK (monthly_price >= 0)");
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT check_subscriptions_billing_cycle_discount_non_negative CHECK (billing_cycle_discount >= 0)");
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT check_subscriptions_final_price_non_negative CHECK (final_price >= 0)");
        DB::statement("ALTER TABLE subscriptions ADD CONSTRAINT check_subscriptions_proration_amount_non_negative CHECK (proration_amount >= 0)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS check_subscriptions_monthly_price_non_negative");
        DB::statement("ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS check_subscriptions_billing_cycle_discount_non_negative");
        DB::statement("ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS check_subscriptions_final_price_non_negative");
        DB::statement("ALTER TABLE subscriptions DROP CONSTRAINT IF EXISTS check_subscriptions_proration_amount_non_negative");
    }
};