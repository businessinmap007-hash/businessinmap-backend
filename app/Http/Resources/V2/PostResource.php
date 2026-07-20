<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lean v2 post payload.
 *
 * The v1 `Posts\PostResource` re-read the viewer out of the database inside
 * four separate fields (`User::whereApiToken($token)->first()` per field, per
 * row) and counted applies/likes/dislikes/comments with a query each — roughly
 * eight extra queries per post on a paginated feed. Here the viewer arrives
 * once on the request and every count is an eager-loaded `withCount`.
 */
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'type' => $this->type ?: 'post',
            'title' => $this->title,
            'body' => $this->body,
            'image' => $this->image ?: null,
            'images' => $this->whenLoaded('images', fn () => $this->images
                ->map(fn ($i) => ['id' => (int) $i->id, 'image' => $i->image])
                ->values()
                ->all()),
            'is_active' => (bool) $this->is_active,
            'share_count' => (int) ($this->share_count ?? 0),
            'likes_count' => (int) ($this->likes_count ?? 0),
            'dislikes_count' => (int) ($this->dislikes_count ?? 0),
            'comments_count' => (int) ($this->comments_count ?? 0),
            'expire_at' => optional($this->expire_at)->toIso8601String(),
            'author' => $this->whenLoaded('user', fn () => [
                'id' => (int) $this->user->id,
                'name' => $this->user->name,
                'logo' => $this->user->logo ?: null,
                'image' => $this->user->image ?: null,
            ]),
            // Set by the controller from a single lookup over the page, never
            // by re-querying per row.
            'my_reaction' => $this->when(isset($this->my_reaction), fn () => $this->my_reaction),
            'is_mine' => $this->when(isset($this->is_mine), fn () => (bool) $this->is_mine),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
