<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('reseller_approval_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('reseller_id')->constrained('resellers');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');

            $table->enum('type', ['large_discount', 'package_change', 'price_override', 'wallet_withdrawal']);
            $table->json('payload')->comment('Details of the requested action');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->text('rejection_reason')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('rejected_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'reseller_id']);
            $table->index(['status', 'created_at']);
            $table->index('uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reseller_approval_requests');
    }
};
