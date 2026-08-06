<?php

namespace App\Notifications;

use App\Channels\SmsChannel;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;

class ServiceReactivatedNotification extends Notification implements ShouldQueue
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
        return (new MailMessage())
                    ->subject('Service Reactivated')
                    ->line('Your internet service has been successfully reactivated.')
                    ->action('View Dashboard', url('/customer/dashboard'));
    }

    public function toSms($notifiable): string
    {
        return "Fiberloop: Good news! Your internet service has been reactivated. Enjoy browsing!";
    }

    public function middleware()
    {
        return [new RateLimited('sms_sends')];
    }
}
