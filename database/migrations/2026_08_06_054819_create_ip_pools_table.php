<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('ip_pools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();

            $table->string('name');
            $table->string('type')->default('static'); // static, dhcp, radius
            $table->string('subnet'); // e.g., 192.168.1.0/24
            $table->string('gateway')->nullable();
            $table->string('dns1')->nullable();
            $table->string('dns2')->nullable();

            $table->foreignId('network_device_id')->nullable()->constrained()->nullOnDelete(); // Router it belongs to

            $table->string('status')->default('active');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_pools');
    }
};
