<?php

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
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('user_id')->nullable()->constrained('users')->comment('Staff user who triggered the notification');

            // Notification details
            $table->string('notifiable_type')->nullable();
            $table->unsignedBigInteger('notifiable_id')->nullable();
            $table->string('type'); // SMS, Email, Push, WhatsApp, etc.
            $table->string('channel')->default('sms'); // sms, email, push, whatsapp

            // Content
            $table->string('subject')->nullable();
            $table->text('message');
            $table->json('data')->nullable();

            // Recipient
            $table->string('to_phone')->nullable();
            $table->string('to_email')->nullable();
            $table->string('to_device_token')->nullable();

            // Status
            $table->boolean('sent')->default(false);
            $table->boolean('delivered')->default(false);
            $table->boolean('failed')->default(false);
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('delivered_at')->nullable();

            // Response
            $table->text('gateway_response')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->text('error_message')->nullable();

            // Metadata
            $table->string('template_used')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->json('metadata')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'type']);
            $table->index(['tenant_id', 'sent']);
            $table->index(['tenant_id', 'created_at']);
            $table->index('to_phone');
            $table->index('to_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
    }
};
