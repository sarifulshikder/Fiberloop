<?php

use App\Enums\CreditNoteStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // Relationships
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('invoice_id')->constrained('invoices');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');

            // Credit note details
            $table->string('credit_note_number')->unique();
            $table->text('reason')->nullable();
            $table->date('issue_date');

            // Amounts (1 = 1 poysha/BDT x 100)
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->unsignedBigInteger('tax_amount')->default(0);
            $table->unsignedBigInteger('total')->default(0);

            // Status
            $table->enum('status', CreditNoteStatus::values())->default(CreditNoteStatus::DRAFT->value);

            // Metadata
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('applied_at')->nullable();
            $table->text('notes')->nullable();

            // Timestamps and soft deletes
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'invoice_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['credit_note_number']);
            $table->index(['issue_date']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
