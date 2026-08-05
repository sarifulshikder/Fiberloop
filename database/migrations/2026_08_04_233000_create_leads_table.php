<?php

use App\Enums\LeadStatus;
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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users');

            // Lead information
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('phone')->unique();
            $table->string('alternate_phone')->nullable();

            // Address
            $table->text('address')->nullable();
            $table->string('latitude', 50)->nullable();
            $table->string('longitude', 50)->nullable();
            $table->string('area')->nullable();
            $table->string('zone')->nullable();

            // Lead status
            $table->enum('status', LeadStatus::values())->default(LeadStatus::NEW->value);

            // Site survey results
            $table->boolean('is_feasible')->nullable();
            $table->foreignId('assigned_olt_id')->nullable()->constrained('olts');
            $table->foreignId('assigned_network_device_id')->nullable()->constrained('network_devices');
            $table->text('feasibility_notes')->nullable();
            $table->dateTime('site_survey_date')->nullable();

            // Conversion
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers');
            $table->dateTime('converted_at')->nullable();

            // Metadata
            $table->enum('source', ['web', 'phone', 'referral', 'field', 'reseller', 'other'])->default('phone');
            $table->string('referral_code')->nullable();
            $table->text('notes')->nullable();

            // Priority
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'assigned_to']);
            $table->index('phone');
            $table->index('email');
            $table->index('uuid');
            $table->index('area');
            $table->index('zone');
            $table->index('converted_customer_id');
            $table->index('assigned_olt_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
