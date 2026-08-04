<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE inventory_items ADD CONSTRAINT check_inventory_items_purchase_price_non_negative CHECK (purchase_price >= 0)");
        DB::statement("ALTER TABLE inventory_items ADD CONSTRAINT check_inventory_items_selling_price_non_negative CHECK (selling_price >= 0)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE inventory_items DROP CONSTRAINT IF EXISTS check_inventory_items_purchase_price_non_negative");
        DB::statement("ALTER TABLE inventory_items DROP CONSTRAINT IF EXISTS check_inventory_items_selling_price_non_negative");
    }
};