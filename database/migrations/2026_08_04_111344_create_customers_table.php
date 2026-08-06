<?php

use App\Enums\ConnectionType;
use App\Enums\CustomerStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            // Profile
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->unique();
            $table->string('alternate_phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();

            // KYC
            $table->string('nid_number')->nullable()->unique();
            $table->string('nid_front_photo')->nullable();
            $table->string('nid_back_photo')->nullable();
            $table->string('signature_photo')->nullable();

            // Addresses
            $table->text('service_address')->nullable();
            $table->string('service_latitude', 50)->nullable();
            $table->string('service_longitude', 50)->nullable();
            $table->text('billing_address')->nullable();

            // Connection details
            $table->enum('connection_type', ConnectionType::values())->default(ConnectionType::PPPOE->value);
            $table->string('radius_username')->nullable()->unique();
            $table->string('radius_password')->nullable();
            $table->string('static_ip')->nullable()->unique();
            $table->string('mac_address')->nullable()->unique();

            // Status and metadata
            $table->enum('status', CustomerStatus::values())->default(CustomerStatus::PENDING->value);
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('suspended_at')->nullable();
            $table->dateTime('terminated_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->text('termination_reason')->nullable();
            $table->json('notes')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index('phone');
            $table->index('nid_number');
            $table->index('radius_username');
            $table->index('email');
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
