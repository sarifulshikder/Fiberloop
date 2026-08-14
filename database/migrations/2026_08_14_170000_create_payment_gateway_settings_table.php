<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();

            // Tenant relationship (null = global / single-tenant ISP default)
            $table->unsignedBigInteger('tenant_id')->nullable();

            // Gateway identifier: bkash | nagad | sslcommerz
            $table->string('gateway', 50);

            // Runtime flags, overridable per tenant
            $table->boolean('enabled')->default(false);
            $table->boolean('sandbox')->default(true);

            // Credentials (app keys, secrets, merchant ids) encrypted at rest
            $table->json('credentials')->nullable();

            $table->timestamps();

            $table->unique(['gateway', 'tenant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
    }
};
