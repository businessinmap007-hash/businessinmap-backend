<?php

namespace App\Http\Resources\V2;

use App\Models\ThreadMessage;
use Illuminate\Http\Resources\Json\JsonResource;

class ThreadMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        $viewerId = (int) ($request->user()->id ?? 0);

        return [
            'id' => (int) $this->id,
            'kind' => $this->kind,
            'body' => $this->body,
            // A system message has no sender, so the app must not render it as
            // coming from anyone.
            'is_mine' => $this->kind === ThreadMessage::KIND_MESSAGE
                && (int) $this->sender_id === $viewerId,
            'sender' => $this->sender_id === null ? null : [
                'id' => (int) $this->sender_id,
                'name' => $this->whenLoaded('sender', fn () => $this->sender?->name),
            ],
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
