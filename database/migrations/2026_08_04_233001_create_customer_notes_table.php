<?php

use App\Enums\NoteType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            // Note content
            $table->enum('type', NoteType::values())->default(NoteType::GENERAL->value);
            $table->text('content');
            $table->string('title')->nullable();

            // Categorization
            $table->enum('category', ['call', 'complaint', 'technician_visit', 'payment', 'support', 'sales', 'other'])->default('support');
            $table->string('reference_type')->nullable(); // e.g., 'ticket', 'invoice', 'subscription'
            $table->unsignedBigInteger('reference_id')->nullable();

            // Status
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_important')->default(false);

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['customer_id', 'type']);
            $table->index(['customer_id', 'created_at']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_notes');
    }
};
