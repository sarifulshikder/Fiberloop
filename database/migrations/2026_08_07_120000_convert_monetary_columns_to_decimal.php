<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Convert all monetary columns from integer (poysha) to decimal(12,2) (BDT 1 = 1 taka)
     * This migration changes the storage format for all money-related fields
     * from integer (which was storing BDT * 100) to decimal(12,2) (storing BDT directly).
     */
    public function up(): void
    {
        // Invoices table
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->default(0.00)->change();
            $table->decimal('tax_amount', 12, 2)->default(0.00)->change();
            $table->decimal('discount_amount', 12, 2)->default(0.00)->change();
            $table->decimal('total', 12, 2)->default(0.00)->change();
            $table->decimal('paid_amount', 12, 2)->default(0.00)->change();
            $table->decimal('outstanding_amount', 12, 2)->default(0.00)->change();
            $table->decimal('proration_amount', 12, 2)->default(0.00)->change();
        });

        // Invoice items table
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)->default(0.00)->change();
            $table->decimal('amount', 12, 2)->default(0.00)->change();
        });

        // Payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->default(0.00)->change();
            $table->decimal('fee_amount', 12, 2)->default(0.00)->change();
            $table->decimal('net_amount', 12, 2)->default(0.00)->change();
        });

        // Subscriptions table
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->decimal('monthly_price', 12, 2)->default(0.00)->change();
            $table->decimal('final_price', 12, 2)->default(0.00)->change();
            $table->decimal('proration_amount', 12, 2)->default(0.00)->change();
        });

        // Customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('wallet_balance', 12, 2)->default(0.00)->change();
        });

        // Packages table
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->default(0.00)->change();
            $table->decimal('installation_fee', 12, 2)->default(0.00)->change();
            $table->decimal('security_deposit', 12, 2)->default(0.00)->change();
        });

        // Resellers table
        Schema::table('resellers', function (Blueprint $table) {
            $table->decimal('commission_amount', 12, 2)->default(0.00)->change();
            $table->decimal('wallet_balance', 12, 2)->default(0.00)->change();
            $table->decimal('total_earnings', 12, 2)->default(0.00)->change();
            $table->decimal('total_withdrawn', 12, 2)->default(0.00)->change();
        });

        // Reseller commission ledger table
        Schema::table('reseller_commission_ledger', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->default(0.00)->change();
            $table->decimal('balance_before', 12, 2)->default(0.00)->change();
            $table->decimal('balance_after', 12, 2)->default(0.00)->change();
        });

        // Wallet transactions table
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->default(0.00)->change();
            $table->decimal('balance_before', 12, 2)->default(0.00)->change();
            $table->decimal('balance_after', 12, 2)->default(0.00)->change();
        });

        // Credit notes table
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->default(0.00)->change();
            $table->decimal('tax_amount', 12, 2)->default(0.00)->change();
            $table->decimal('total', 12, 2)->default(0.00)->change();
        });

        // Refunds table
        Schema::table('refunds', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->default(0.00)->change();
            $table->decimal('fee_amount', 12, 2)->default(0.00)->change();
            $table->decimal('net_amount', 12, 2)->default(0.00)->change();
        });

        // Procurements table
        Schema::table('procurements', function (Blueprint $table) {
            $table->decimal('subtotal', 12, 2)->default(0.00)->change();
            $table->decimal('tax_amount', 12, 2)->default(0.00)->change();
            $table->decimal('shipping_cost', 12, 2)->default(0.00)->change();
            $table->decimal('total_amount', 12, 2)->default(0.00)->change();
        });

        // Procurement items table
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->decimal('unit_price', 12, 2)->default(0.00)->change();
            $table->decimal('total_price', 12, 2)->default(0.00)->change();
        });

        // Add-ons table
        Schema::table('add_ons', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->default(0.00)->change();
        });

        // Package zones table
        Schema::table('package_zones', function (Blueprint $table) {
            $table->decimal('custom_price', 12, 2)->default(0.00)->change();
        });

        // Subscription pricing overrides table
        Schema::table('subscription_pricing_overrides', function (Blueprint $table) {
            $table->decimal('override_price', 12, 2)->default(0.00)->change();
            $table->decimal('override_installation_fee', 12, 2)->default(0.00)->change();
            $table->decimal('override_security_deposit', 12, 2)->default(0.00)->change();
        });

        // Inventory items table
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('purchase_price', 12, 2)->default(0.00)->change();
            $table->decimal('selling_price', 12, 2)->default(0.00)->change();
        });

        // Payment reconciliations table
        Schema::table('payment_reconciliations', function (Blueprint $table) {
            $table->decimal('recorded_amount', 12, 2)->default(0.00)->change();
            $table->decimal('settlement_amount', 12, 2)->default(0.00)->change();
        });

        // Package change requests table
        Schema::table('package_change_requests', function (Blueprint $table) {
            $table->decimal('proration_amount', 12, 2)->default(0.00)->change();
        });

        // Promo codes table
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->decimal('discount_value', 12, 2)->default(0.00)->change();
        });
    }

    /**
     * Reverse the migration - convert decimal back to integer
     */
    public function down(): void
    {
        // Invoices table
        Schema::table('invoices', function (Blueprint $table) {
            $table->bigInteger('subtotal')->change();
            $table->bigInteger('tax_amount')->change();
            $table->bigInteger('discount_amount')->change();
            $table->bigInteger('total')->change();
            $table->bigInteger('paid_amount')->change();
            $table->bigInteger('outstanding_amount')->change();
            $table->bigInteger('proration_amount')->change();
        });

        // Invoice items table
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->bigInteger('unit_price')->change();
            $table->bigInteger('amount')->change();
        });

        // Payments table
        Schema::table('payments', function (Blueprint $table) {
            $table->bigInteger('amount')->change();
            $table->bigInteger('fee_amount')->change();
            $table->bigInteger('net_amount')->change();
        });

        // Subscriptions table
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->bigInteger('monthly_price')->change();
            $table->bigInteger('final_price')->change();
            $table->bigInteger('proration_amount')->change();
        });

        // Customers table
        Schema::table('customers', function (Blueprint $table) {
            $table->bigInteger('wallet_balance')->change();
        });

        // Packages table
        Schema::table('packages', function (Blueprint $table) {
            $table->bigInteger('price')->change();
            $table->bigInteger('installation_fee')->change();
            $table->bigInteger('security_deposit')->change();
        });

        // Resellers table
        Schema::table('resellers', function (Blueprint $table) {
            $table->bigInteger('commission_amount')->change();
            $table->bigInteger('wallet_balance')->change();
            $table->bigInteger('total_earnings')->change();
            $table->bigInteger('total_withdrawn')->change();
        });

        // Reseller commission ledger table
        Schema::table('reseller_commission_ledger', function (Blueprint $table) {
            $table->bigInteger('amount')->change();
            $table->bigInteger('balance_before')->change();
            $table->bigInteger('balance_after')->change();
        });

        // Wallet transactions table
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->bigInteger('amount')->change();
            $table->bigInteger('balance_before')->change();
            $table->bigInteger('balance_after')->change();
        });

        // Credit notes table
        Schema::table('credit_notes', function (Blueprint $table) {
            $table->bigInteger('subtotal')->change();
            $table->bigInteger('tax_amount')->change();
            $table->bigInteger('total')->change();
        });

        // Refunds table
        Schema::table('refunds', function (Blueprint $table) {
            $table->bigInteger('amount')->change();
            $table->bigInteger('fee_amount')->change();
            $table->bigInteger('net_amount')->change();
        });

        // Procurements table
        Schema::table('procurements', function (Blueprint $table) {
            $table->bigInteger('subtotal')->change();
            $table->bigInteger('tax_amount')->change();
            $table->bigInteger('shipping_cost')->change();
            $table->bigInteger('total_amount')->change();
        });

        // Procurement items table
        Schema::table('procurement_items', function (Blueprint $table) {
            $table->bigInteger('unit_price')->change();
            $table->bigInteger('total_price')->change();
        });

        // Add-ons table
        Schema::table('add_ons', function (Blueprint $table) {
            $table->bigInteger('price')->change();
        });

        // Package zones table
        Schema::table('package_zones', function (Blueprint $table) {
            $table->bigInteger('custom_price')->change();
        });

        // Subscription pricing overrides table
        Schema::table('subscription_pricing_overrides', function (Blueprint $table) {
            $table->bigInteger('override_price')->change();
            $table->bigInteger('override_installation_fee')->change();
            $table->bigInteger('override_security_deposit')->change();
        });

        // Inventory items table
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->bigInteger('purchase_price')->change();
            $table->bigInteger('selling_price')->change();
        });

        // Payment reconciliations table
        Schema::table('payment_reconciliations', function (Blueprint $table) {
            $table->bigInteger('recorded_amount')->change();
            $table->bigInteger('settlement_amount')->change();
        });

        // Package change requests table
        Schema::table('package_change_requests', function (Blueprint $table) {
            $table->bigInteger('proration_amount')->change();
        });

        // Promo codes table
        Schema::table('promo_codes', function (Blueprint $table) {
            $table->bigInteger('discount_value')->change();
        });
    }
};
