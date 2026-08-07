<?php

namespace App\Notifications;

use App\Models\CustomerDataExportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerDataExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CustomerDataExportRequest $exportRequest)
    {
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $customer = $this->exportRequest->customer;

        return (new MailMessage())
            ->subject('Your Data Export is Ready')
            ->greeting('Hello ' . $customer->first_name . ',')
            ->line('Your data export request has been completed and is ready for download.')
            ->line('You requested to export your data on ' . $this->exportRequest->requested_at->format('M d, Y H:i'))
            ->line('Export Request ID: ' . $this->exportRequest->uuid)
            ->line('Status: Completed')
            ->line('Format: ' . strtoupper($this->exportRequest->format))
            ->action('Download Your Data', url('/customer/data/export/' . $this->exportRequest->uuid . '/download'))
            ->line('This download link will expire on ' . $this->exportRequest->download_expires_at?->format('M d, Y H:i'))
            ->line('If you did not request this export, please contact support immediately.')
            ->line('Thank you for using our service!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'customer_data_export_ready',
            'export_request_id' => $this->exportRequest->uuid,
            'status' => 'completed',
            'download_url' => $this->exportRequest->download_url,
            'download_expires_at' => $this->exportRequest->download_expires_at?->toDateTimeString(),
            'format' => $this->exportRequest->format,
            'requested_at' => $this->exportRequest->requested_at->toDateTimeString(),
            'completed_at' => $this->exportRequest->completed_at?->toDateTimeString(),
        ];
    }
}
