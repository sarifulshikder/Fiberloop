<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
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
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            // Ticket number
            $table->string('ticket_number')->unique();

            // Subject and description
            $table->string('subject');
            $table->text('description');

            // Category
            $table->string('category')->default('technical');
            $table->string('sub_category')->nullable();

            // Priority and status
            $table->enum('priority', TicketPriority::values())->default(TicketPriority::MEDIUM->value);
            $table->enum('status', TicketStatus::values())->default(TicketStatus::OPEN->value);

            // SLA tracking
            $table->dateTime('due_at')->nullable()->comment('SLA deadline');
            $table->dateTime('resolved_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->integer('response_time_minutes')->nullable()->comment('Minutes taken to first response');
            $table->integer('resolution_time_minutes')->nullable()->comment('Minutes taken to resolve');

            // Source
            $table->string('source')->default('customer_portal'); // customer_portal, email, phone, walk_in

            // Related entities
            $table->foreignId('related_invoice_id')->nullable()->constrained('invoices');
            $table->foreignId('related_payment_id')->nullable()->constrained('payments');

            // Metadata
            $table->json('attachments')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('tags')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'priority']);
            $table->index(['assigned_to', 'status']);
            $table->index('ticket_number');
            $table->index(['due_at', 'status']);
            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
