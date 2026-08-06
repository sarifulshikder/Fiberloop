<?php

namespace App\Notifications;

use App\Models\CustomerDataDeletionRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerDataDeletionConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CustomerDataDeletionRequest $deletionRequest)
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
        $customer = $this->deletionRequest->customer;
        
        return (new MailMessage)
            ->subject('Confirm Your Data Deletion Request')
            ->greeting('Hello ' . $customer->first_name . ',')
            ->line('We have received your request to delete your personal data from our systems.')
            ->line('Request ID: ' . $this->deletionRequest->uuid)
            ->line('Scope: ' . ucfirst($this->deletionRequest->scope))
            ->line('Requested on: ' . $this->deletionRequest->requested_at->format('M d, Y H:i'))
            ->line('')
            ->line('**This action is permanent and cannot be undone.**')
            ->line('Once confirmed, your personal data will be permanently deleted within 24 hours.')
            ->line('')
            ->line('Financial records and transaction history will be preserved for legal and accounting purposes, but all personal identifiers will be removed.')
            ->line('')
            ->action('Confirm Data Deletion', url('/customer/data/deletion/' . $this->deletionRequest->uuid . '/confirm?token=' . $this->deletionRequest->confirmation_token))
            ->line('')
            ->line('If you did not request this deletion, please contact support immediately.')
            ->line('If you have any questions, please contact our support team before confirming.')
            ->line('Thank you for using our service.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'customer_data_deletion_confirmation',
            'deletion_request_id' => $this->deletionRequest->uuid,
            'status' => 'confirmation_required',
            'scope' => $this->deletionRequest->scope,
            'confirmation_token' => $this->deletionRequest->confirmation_token,
            'requested_at' => $this->deletionRequest->requested_at->toDateTimeString(),
        ];
    }
}
