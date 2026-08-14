<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The encrypted:array cast stores a base64 ciphertext string, which is not
     * valid JSON. The credentials column must be text, not json.
     */
    public function up(): void
    {
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->text('credentials')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->json('credentials')->nullable()->change();
        });
    }
};
