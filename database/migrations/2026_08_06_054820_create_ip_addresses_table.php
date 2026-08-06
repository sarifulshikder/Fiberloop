<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('ip_pool_id')->constrained()->cascadeOnDelete();

            $table->string('ip_address'); // 192.168.1.50
            $table->string('mac_address')->nullable();

            $table->string('status')->default('available'); // available, reserved, active, blocked

            // For static assignment to a customer/subscription
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamp('last_seen_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['ip_pool_id', 'ip_address']);
            $table->index('tenant_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_addresses');
    }
};
