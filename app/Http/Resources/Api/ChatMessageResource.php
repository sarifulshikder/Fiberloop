<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'conversation_id' => $this->conversation_id,
            'sender_id' => $this->sender_id,
            'sender_type' => $this->sender_type,
            'sender_name' => $this->sender?->name ?? 'Unknown',
            'sender_avatar' => $this->sender?->avatar ?? null,
            'content' => $this->content,
            'attachment_path' => $this->attachment_path,
            'attachment_type' => $this->attachment_type,
            'is_read' => $this->is_read,
            'read_at' => $this->read_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
