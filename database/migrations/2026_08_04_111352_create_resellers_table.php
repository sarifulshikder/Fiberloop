<?php

use App\Enums\ResellerStatus;
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
        Schema::create('resellers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('parent_id')->nullable()->comment('Parent reseller for hierarchy');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            // Reseller details
            $table->string('name');
            $table->string('code')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->unique();
            $table->string('alternate_phone')->nullable();
            $table->text('address')->nullable();

            // Business details
            $table->enum('status', ResellerStatus::values())->default(ResellerStatus::ACTIVE->value);
            $table->unsignedBigInteger('commission_rate')->default(0)->comment('Percentage as integer (e.g., 10 = 10%)');
            $table->unsignedBigInteger('commission_amount')->default(0)->comment('Flat commission per customer (1 = 1 taka)');

            // Financial
            $table->unsignedBigInteger('wallet_balance')->default(0)->comment('1 = 1 taka');
            $table->unsignedBigInteger('total_earnings')->default(0)->comment('1 = 1 taka');
            $table->unsignedBigInteger('total_withdrawn')->default(0)->comment('1 = 1 taka');

            // Contract
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->text('contract_terms')->nullable();

            // Status tracking
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('suspended_at')->nullable();
            $table->dateTime('terminated_at')->nullable();
            $table->text('suspension_reason')->nullable();
            $table->text('termination_reason')->nullable();

            // Metadata
            $table->json('notes')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'parent_id']);
            $table->index(['tenant_id', 'status']);
            $table->index('parent_id');
            $table->index('code');
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resellers');
    }
};
