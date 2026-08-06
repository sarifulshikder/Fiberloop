<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ChatService
{
    /**
     * Create a new chat conversation.
     */
    public function createConversation(Customer $customer, string $subject, string $message): ChatConversation
    {
        $conversation = ChatConversation::create([
            'tenant_id' => $customer->tenant_id,
            'uuid' => (string) Str::orderedUuid(),
            'customer_id' => $customer->id,
            'subject' => $subject,
            'status' => 'open',
            'priority' => 'medium',
            'source' => 'customer_portal',
            'is_read_by_customer' => true,
            'is_read_by_agent' => false,
            'created_by' => $customer->user?->id ?? Auth::id(),
        ]);

        // Create the first message
        $this->createMessage($conversation, $customer->user?->id ?? Auth::id(), 'customer', $message);

        return $conversation;
    }

    /**
     * Create a new chat message.
     */
    public function createMessage(ChatConversation $conversation, int $senderId, string $senderType, string $content, ?string $attachmentPath = null, ?string $attachmentType = null): ChatMessage
    {
        $message = ChatMessage::create([
            'tenant_id' => $conversation->tenant_id,
            'uuid' => (string) Str::orderedUuid(),
            'conversation_id' => $conversation->id,
            'sender_id' => $senderId,
            'sender_type' => $senderType,
            'content' => $content,
            'attachment_path' => $attachmentPath,
            'attachment_type' => $attachmentType,
            'is_read' => false,
            'created_by' => $senderId,
        ]);

        // Update conversation timestamps
        $conversation->update([
            'updated_at' => now(),
            'updated_by' => $senderId,
        ]);

        // Mark conversation as unread for the other party
        if ($senderType === 'customer') {
            $conversation->update(['is_read_by_agent' => false]);
        } else {
            $conversation->update(['is_read_by_customer' => false]);
        }

        // Fire event for real-time notifications
        event(new ChatMessageSent($message));

        return $message;
    }

    /**
     * Get conversations for a customer.
     */
    public function getCustomerConversations(Customer $customer, int $limit = 20): Collection
    {
        return ChatConversation::forCustomer($customer->id)
            ->with(['customer', 'assignedTo', 'latestMessage'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get messages for a conversation.
     */
    public function getConversationMessages(ChatConversation $conversation, int $limit = 50): Collection
    {
        return ChatMessage::forConversation($conversation->id)
            ->with(['sender', 'conversation'])
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Mark conversation messages as read.
     */
    public function markConversationAsRead(ChatConversation $conversation, int $userId, string $userType): void
    {
        $field = $userType === 'customer' ? 'is_read_by_customer' : 'is_read_by_agent';

        $conversation->update([$field => true]);

        // Mark all unread messages as read
        ChatMessage::forConversation($conversation->id)
            ->where('is_read', false)
            ->where('sender_type', '!=', $userType)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
    }

    /**
     * Close a conversation.
     */
    public function closeConversation(ChatConversation $conversation, int $userId): void
    {
        $conversation->update([
            'status' => 'closed',
            'closed_at' => now(),
            'updated_by' => $userId,
        ]);
    }

    /**
     * Get unread message count for a customer.
     */
    public function getUnreadCount(Customer $customer): int
    {
        return ChatMessage::whereHas('conversation', function ($query) use ($customer) {
            $query->forCustomer($customer->id);
        })
            ->where('is_read', false)
            ->where('sender_type', 'agent')
            ->count();
    }

    /**
     * Get online support agents.
     */
    public function getOnlineAgents(): Collection
    {
        // Get users with support_agent role who are online
        // This would typically integrate with presence/echo
        return User::role('support_agent')
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(10)
            ->get();
    }
}
