<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a chat message is sent (for real-time chat).
 * This event is broadcast to enable real-time chat updates.
 */
class ChatMessageSent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * The chat message instance.
     */
    public ChatMessage $message;

    /**
     * Create a new event instance.
     */
    public function __construct(ChatMessage $message)
    {
        $this->message = $message->load(['sender', 'conversation.customer']);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Broadcast to the conversation channel and the customer's private channel
        return [
            new Channel('chat.conversation.' . $this->message->conversation_id),
            new Channel('chat.customer.' . $this->message->conversation->customer_id),
            new Channel('chat.agent.' . $this->message->conversation->assigned_to),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'uuid' => $this->message->uuid,
                'conversation_id' => $this->message->conversation_id,
                'sender_id' => $this->message->sender_id,
                'sender_type' => $this->message->sender_type,
                'sender_name' => $this->message->sender?->name ?? 'Unknown',
                'content' => $this->message->content,
                'attachment_path' => $this->message->attachment_path,
                'attachment_type' => $this->message->attachment_type,
                'is_read' => $this->message->is_read,
                'created_at' => $this->message->created_at?->toDateTimeString(),
            ],
            'conversation' => [
                'id' => $this->message->conversation->id,
                'uuid' => $this->message->conversation->uuid,
                'customer_id' => $this->message->conversation->customer_id,
                'subject' => $this->message->conversation->subject,
                'status' => $this->message->conversation->status,
                'is_read_by_customer' => $this->message->conversation->is_read_by_customer,
                'is_read_by_agent' => $this->message->conversation->is_read_by_agent,
            ],
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'chat.message.sent';
    }
}
