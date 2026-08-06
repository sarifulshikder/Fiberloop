<?php

namespace App\Channels;

use App\Models\NotificationsLog;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class SmsChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        // Get the SMS message content
        $message = $notification->toSms($notifiable);

        // Get the phone number
        $phone = $notifiable->routeNotificationFor('sms', $notification);

        if (! $phone) {
            return;
        }

        // 1. Log intended notification
        $log = NotificationsLog::create([
            'tenant_id' => $notifiable->tenant_id ?? null,
            'customer_id' => $notifiable->id ?? null, // Assuming notifiable is customer for now
            'notifiable_type' => get_class($notifiable),
            'notifiable_id' => $notifiable->id,
            'type' => 'sms',
            'channel' => 'sms',
            'message' => $message,
            'to_phone' => $phone,
            'sent' => false,
        ]);

        // 2. Generic contract/mock for SMS Gateway Provider sending logic
        try {
            Log::info("Sending SMS to {$phone}: {$message}");

            // MOCK: simulate gateway call
            $gatewayResponse = ['status' => 'success', 'reference' => uniqid('sms_')];

            // 3. Update log
            $log->update([
                'sent' => true,
                'sent_at' => now(),
                'delivered' => true,
                'delivered_at' => now(),
                'gateway_response' => json_encode($gatewayResponse),
                'gateway_reference' => $gatewayResponse['reference'],
            ]);
        } catch (\Exception $e) {
            Log::error("SMS sending failed for {$phone}: " . $e->getMessage());
            $log->update([
                'failed' => true,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
