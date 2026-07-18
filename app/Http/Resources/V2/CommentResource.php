<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lean v2 comment payload.
 *
 * The v1 `CommentResource` embedded a whole `PostResource` in every comment —
 * which itself re-queried the viewer and counted likes/applies/comments — so
 * listing ten comments dragged the entire post payload along ten times. The
 * caller already knows which post it asked about.
 */
class CommentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'post_id' => (int) $this->post_id,
            'parent_id' => (int) ($this->parent_id ?? 0),
            'comment' => $this->comment,
            'status' => $this->status,
            'is_private' => $this->status === 'private',
            'replies_count' => (int) ($this->children_count ?? 0),
            'author' => $this->whenLoaded('user', fn () => [
                'id' => (int) $this->user->id,
                'name' => $this->user->name,
                'logo' => $this->user->logo ?: null,
                'image' => $this->user->image ?: null,
            ]),
            // Set by the controller from the request's viewer, not re-queried.
            'is_mine' => $this->when(isset($this->is_mine), fn () => (bool) $this->is_mine),
            'can_delete' => $this->when(isset($this->can_delete), fn () => (bool) $this->can_delete),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
