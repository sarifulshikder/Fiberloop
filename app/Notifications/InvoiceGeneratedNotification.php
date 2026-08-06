<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;

class InvoiceGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function via($notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())
                    ->subject('Your Invoice is Ready')
                    ->line('Your invoice for the upcoming billing cycle has been generated.')
                    ->line('Amount: ' . ($this->invoice->total / 100) . ' BDT')
                    ->line('Due Date: ' . $this->invoice->due_date->format('Y-m-d'))
                    ->action('View Invoice', url('/customer/invoices/' . $this->invoice->id));
    }

    public function toSms($notifiable): string
    {
        return "Fiberloop: Your invoice of " . ($this->invoice->total / 100) . " BDT is ready. Due date: " . $this->invoice->due_date->format('Y-m-d');
    }

    public function middleware()
    {
        // Rate limit to prevent blowing through provider limits for bulk sends
        return [new RateLimited('sms_sends')];
    }
}
