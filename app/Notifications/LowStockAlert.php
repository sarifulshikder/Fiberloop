<?php

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Notification sent when inventory stock is low.
 */
class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Collection $alerts,
        public Tenant $tenant
    ) {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        // Use mail and database channels
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $subject = 'Low Stock Alert for ' . ($this->tenant->name ?? 'Inventory');

        $mail = (new MailMessage())
            ->subject($subject)
            ->greeting('Hello!')
            ->line('The following inventory items are running low:')
            ->line('');

        foreach ($this->alerts as $alert) {
            $severityEmoji = match ($alert['severity']) {
                'critical' => '🔴',
                'warning' => '🟡',
                'low' => '🟠',
                default => '⚪',
            };

            $mail->line($severityEmoji . ' **' . ucwords(str_replace('_', ' ', $alert['item_type'])) . '**: ' .
                $alert['current_count'] . ' in stock (threshold: ' . $alert['threshold'] . ')');
        }

        $mail->line('');
        $mail->line('Please replenish these items as soon as possible.');
        $mail->action('View Inventory', url('/admin/inventory'));
        $mail->line('Thank you for using Fiberloop!');

        return $mail;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'Low stock alert',
            'tenant_id' => $this->tenant->id,
            'tenant_name' => $this->tenant->name,
            'alerts' => $this->alerts->toArray(),
            'severity' => $this->alerts->pluck('severity')->max(),
            'created_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Get the notification's database type.
     */
    public function databaseType(object $notifiable): string
    {
        return 'inventory_low_stock';
    }
}
