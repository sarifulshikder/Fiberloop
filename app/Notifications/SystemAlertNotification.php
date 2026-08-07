<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification for system alerts sent via email.
 */
class SystemAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The alert data.
     */
    protected array $alertData;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $alertData)
    {
        $this->alertData = $alertData;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $severity = strtoupper($this->alertData['severity']);
        $category = strtoupper($this->alertData['category']);

        return (new MailMessage)
            ->subject("[$severity] {$this->alertData['title']}")
            ->greeting("System Alert: $severity")
            ->line($this->alertData['title'])
            ->line($this->alertData['message'])
            ->line('**Category:** ' . $category)
            ->line('**Timestamp:** ' . $this->alertData['timestamp'])
            ->line('**Alert ID:** ' . ($this->alertData['id'] ?? 'N/A'))
            ->action('View Dashboard', url('/admin'))
            ->line('Please investigate and resolve this issue as soon as possible.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->alertData;
    }
}
