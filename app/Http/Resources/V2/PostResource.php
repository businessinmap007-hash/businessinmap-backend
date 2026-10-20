<?php

namespace App\Http\Resources\V2;

use App\Models\ProjectShowcase;
use App\Services\Posts\PostSubjectService;
use App\Services\Projects\ProjectShowcaseService;
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
            // A linked post falls back to the item's name, in the reader's
            // language — that is the point of linking. Unlinked posts (all of
            // them today) keep their own free-text title.
            'title' => $this->title ?: $this->subjects()->nameFor($this->resource),
            // null when unlinked; the client renders the action button only
            // when this is present.
            'subject' => $this->subjects()->present($this->resource),
            'body' => $this->body,
            'image' => $this->image ?: null,
            'images' => $this->whenLoaded('images', fn () => $this->images
                ->map(fn ($i) => ['id' => (int) $i->id, 'image' => $i->image, 'source' => $i->source])
                ->values()
                ->all()),
            // «نتائج موثَّقة»: a project shown with its customer's consent — the progress is read live, so it grows by itself.
            'showcase' => $this->when($this->subject_type === ProjectShowcase::POST_SUBJECT, fn () => $this->showcase()),
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

    private function showcase(): ?array
    {
        $row = ProjectShowcase::query()->find((int) $this->subject_id);

        return $row ? app(ProjectShowcaseService::class)->payload($row) : null;
    }

    /**
     * One instance per request, so the subjects the controller preloaded are
     * still cached here — resolving a fresh one per row would re-query.
     */
    private function subjects(): PostSubjectService
    {
        return app(PostSubjectService::class);
    }
}
