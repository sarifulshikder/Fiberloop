<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'customer_id' => $this->customer_id,
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'source' => $this->source,
            'is_read_by_customer' => $this->is_read_by_customer,
            'is_read_by_agent' => $this->is_read_by_agent,
            'resolved_at' => $this->resolved_at?->toDateTimeString(),
            'closed_at' => $this->closed_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'assigned_to' => $this->whenLoaded('assignedTo') ? [
                'id' => $this->assigned_to->id,
                'name' => $this->assigned_to->name,
                'avatar' => $this->assigned_to->avatar,
            ] : null,
            'messages' => ChatMessageResource::collection($this->whenLoaded('messages')),
            'messages_count' => $this->messages->count() ?? 0,
            'unread_messages_count' => $this->messages ? $this->messages->where('is_read', false)->count() : 0,
            'latest_message' => $this->whenLoaded('latestMessage') ? new ChatMessageResource($this->latestMessage) : null,
        ];
    }
}
