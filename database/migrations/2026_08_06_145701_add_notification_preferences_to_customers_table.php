<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('promotional_sms_opt_in')->default(true)->after('wallet_balance');
            $table->boolean('promotional_email_opt_in')->default(true)->after('promotional_sms_opt_in');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['promotional_sms_opt_in', 'promotional_email_opt_in']);
        });
    }
};
