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
        Schema::create('radius_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('customer_id')->unique()->constrained('customers');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions');
            $table->foreignId('created_by')->nullable()->constrained('users');

            // FreeRADIUS credentials (these map to radcheck table)
            $table->string('radius_username')->unique();
            $table->string('radius_password')->nullable();
            $table->string('radius_group')->default('default');

            // IP assignment
            $table->string('framed_ip_address')->nullable();
            $table->string('framed_ip_netmask')->nullable();
            $table->string('framed_route')->nullable();

            // Session limits
            $table->integer('session_timeout')->nullable()->comment('Session timeout in seconds');
            $table->integer('idle_timeout')->nullable()->comment('Idle timeout in seconds');
            $table->integer('max_input_octets')->nullable()->comment('Max download in bytes');
            $table->integer('max_output_octets')->nullable()->comment('Max upload in bytes');
            $table->integer('max_total_octets')->nullable()->comment('Max total in bytes');

            // Bandwidth limits (for CoA - Change of Authorization)
            $table->unsignedInteger('max_download_speed')->nullable()->comment('In Kbps');
            $table->unsignedInteger('max_upload_speed')->nullable()->comment('In Kbps');

            // Connection type
            $table->string('connection_type')->default('pppoe');

            // Status
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_auth_at')->nullable();
            $table->dateTime('last_acct_start_at')->nullable();
            $table->dateTime('last_acct_stop_at')->nullable();

            // Session tracking
            $table->string('nas_ip_address')->nullable()->comment('NAS IP that last authenticated this user');
            $table->string('nas_port')->nullable();
            $table->string('session_id')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'customer_id']);
            $table->index('customer_id');
            $table->index('radius_username');
            $table->index(['tenant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('radius_customers');
    }
};
