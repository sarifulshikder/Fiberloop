<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Channels\SmsChannel;
use App\Models\Subscription;
use Illuminate\Queue\Middleware\RateLimited;

class ServiceSuspendedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $subscription;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    public function via($notifiable): array
    {
        return ['mail', SmsChannel::class];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('Service Suspended')
                    ->line('Your internet service has been suspended due to non-payment.')
                    ->line('Please clear your dues to restore the connection.')
                    ->action('Pay Now', url('/customer/invoices'));
    }

    public function toSms($notifiable): string
    {
        return "Fiberloop: Your internet service has been suspended. Please pay your outstanding dues to restore connection.";
    }
    
    public function middleware()
    {
        return [new RateLimited('sms_sends')];
    }
}
