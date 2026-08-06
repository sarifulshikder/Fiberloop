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
            $table->string('fcm_token', 255)->nullable()->after('promotional_email_opt_in');
            $table->timestamp('fcm_token_verified_at')->nullable()->after('fcm_token');
            $table->timestamp('last_push_notification_at')->nullable()->after('fcm_token_verified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['fcm_token', 'fcm_token_verified_at', 'last_push_notification_at']);
        });
    }
};
