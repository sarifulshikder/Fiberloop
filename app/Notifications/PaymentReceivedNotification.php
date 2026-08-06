<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;

class PaymentReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $payment;

    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
    }

    public function via($notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage())
                    ->subject('Payment Received')
                    ->line('We have successfully received your payment.')
                    ->line('Amount: ' . ($this->payment->amount / 100) . ' BDT')
                    ->action('View Dashboard', url('/customer/dashboard'));
    }

    public function toSms($notifiable): string
    {
        return "Fiberloop: We received your payment of " . ($this->payment->amount / 100) . " BDT. Thank you!";
    }

    public function middleware()
    {
        return [new RateLimited('sms_sends')];
    }
}
