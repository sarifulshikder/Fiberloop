<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE packages ADD CONSTRAINT check_packages_price_non_negative CHECK (price >= 0)");
        DB::statement("ALTER TABLE packages ADD CONSTRAINT check_packages_installation_fee_non_negative CHECK (installation_fee >= 0)");
        DB::statement("ALTER TABLE packages ADD CONSTRAINT check_packages_security_deposit_non_negative CHECK (security_deposit >= 0)");
        DB::statement("ALTER TABLE packages ADD CONSTRAINT check_packages_tax_rate_non_negative CHECK (tax_rate >= 0)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE packages DROP CONSTRAINT IF EXISTS check_packages_price_non_negative");
        DB::statement("ALTER TABLE packages DROP CONSTRAINT IF EXISTS check_packages_installation_fee_non_negative");
        DB::statement("ALTER TABLE packages DROP CONSTRAINT IF EXISTS check_packages_security_deposit_non_negative");
        DB::statement("ALTER TABLE packages DROP CONSTRAINT IF EXISTS check_packages_tax_rate_non_negative");
    }
};