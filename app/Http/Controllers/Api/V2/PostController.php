<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Http\Resources\V2\PostResource;
use App\Models\Image;
use App\Models\Like;
use App\Models\FeedPost;
use App\Models\Post;
use App\Services\Media\ImageUploadService;
use App\Services\Posts\PostAudienceService;
use App\Services\Posts\PostSubjectService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Posts — the social feed, ported from v1.
 *
 * v1's `Api\V1\PostController` is routed and live but broken and slow:
 *   - `getPosts()` with a token dies on `User::byToken()` (undefined), so the
 *     authenticated feed 500s; verified against the running server.
 *   - the guest branch calls `Post::get()` — the whole table into memory —
 *     to sort by distance.
 *   - `PostResource` re-reads the viewer from the DB inside four separate
 *     fields and counts likes/applies/comments with a query each.
 *   - `store()`/`update()` assign `$request->images` *strings* straight onto
 *     `Image->image`, and the endpoint meant to produce those strings
 *     (`Api\V1\ImageController`) was never routed.
 *
 * This rebuilds the surface on the real tables: paginated at the database,
 * one query per page for reactions, and real multipart uploads through
 * ImageUploadService.
 */
final class PostController extends Controller
{
    /**
     * `comments_count` deliberately counts PUBLIC top-level comments only.
     *
     * A private comment is visible to its author and the post's owner alone
     * (CommentVisibilityService), so counting them would advertise a number
     * most readers cannot open. One uniform, honest figure beats a per-viewer
     * count that would cost a query per row on the feed.
     */
    private static function counts(): array
    {
        return [
            'likes',
            'dislikes',
            'comments as comments_count' => fn ($q) => $q->where('status', 'public'),
        ];
    }

    public function __construct(
        private readonly PostAudienceService $audience,
        private readonly ImageUploadService $uploads,
        private readonly PostSubjectService $subjects,
    ) {
    }

    /**
     * GET /api/v2/posts/subject-options — what the caller can link a post to,
     * derived from what they actually own (menu items, bookable units).
     *
     * Empty is a normal answer, and the common one right now: a business with
     * no menu and no bookable units has nothing to advertise but free text.
     */
    public function subjectOptions(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => ['options' => $this->subjects->optionsFor($request->user())],
        ]);
    }

    /**
     * The chosen subject, verified to belong to the caller.
     *
     * 422 rather than silently dropping the link: a business that picked an
     * item it does not own must be told, not quietly published without it.
     *
     * @return array{type: ?string, id: ?int}
     */
    private function ownedSubject(array $data, int $businessId): array
    {
        $type = $data['subject_type'] ?? null;
        $id = (int) ($data['subject_id'] ?? 0);

        if ($type === null || $id <= 0) {
            return ['type' => null, 'id' => null];
        }

        if (! $this->subjects->resolveOwned($type, $id, $businessId)) {
            throw ValidationException::withMessages([
                'subject_id' => __('لا يمكنك الإعلان عن عنصر لا يخصك.'),
            ]);
        }

        return ['type' => $type, 'id' => $id];
    }

    /**
     * GET /api/v2/posts — the feed.
     *
     * Signed in: only authors this user is entitled to see (see
     * PostAudienceService), never their own. Guest: recent public posts,
     * optionally nearest-first when lat/lng are supplied.
     */
    public function index(Request $request)
    {
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'q' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $this->viewer($request);
        $perPage = (int) ($data['per_page'] ?? 15);

        $query = $this->baseQuery();

        if ($user) {
            $authorIds = $this->audience->authorIdsFor($user);

            // No entitled authors means an empty feed, not the whole table.
            if ($authorIds === []) {
                return PostResource::collection(
                    $this->baseQuery()->whereRaw('1 = 0')->paginate($perPage)
                );
            }

            $query->whereIn('user_id', $authorIds);
        }

        $q = trim((string) ($data['q'] ?? ''));

        if ($q !== '') {
            $query->where(fn ($w) => $w
                ->where('title', 'like', "%{$q}%")
                ->orWhere('body', 'like', "%{$q}%"));
        }

        $this->applyOrdering($query, $data);

        $posts = $query->paginate($perPage)->appends($request->query());

        return PostResource::collection($this->decorate($posts, $user));
    }

    /** GET /api/v2/posts/mine — this account's own posts, active or not. */
    public function mine(Request $request)
    {
        $posts = $this->baseQuery()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->paginate((int) $request->get('per_page', 15))
            ->appends($request->query());

        return PostResource::collection($this->decorate($posts, $request->user()));
    }

    /** GET /api/v2/posts/{post} — one post. */
    public function show(Request $request, FeedPost $post)
    {
        $post->loadMissing(['user:id,name,logo,image', 'images'])
            ->loadCount(self::counts());

        $this->attachViewerState(collect([$post]), $this->viewer($request));

        return response()->json(['success' => true, 'data' => new PostResource($post)]);
    }

    /** POST /api/v2/posts — publish a post, with optional images. */
    public function store(Request $request)
    {
        $data = $request->validate([
            // Optional now that a post can carry a subject: a linked post takes
            // its heading from the item. Still required when nothing is linked,
            // which is every post today — menu_items is empty platform-wide.
            'title' => ['required_without:subject_type', 'nullable', 'string', 'max:191'],
            'body' => ['required', 'string'],
            'expire_at' => ['nullable', 'date'],
            'subject_type' => ['nullable', 'required_with:subject_id', 'string', Rule::in($this->subjects->types())],
            'subject_id' => ['nullable', 'required_with:subject_type', 'integer', 'min:1'],
            'image' => ['nullable', ...ImageUploadService::validationRules()],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ImageUploadService::validationRules(),
        ]);

        $user = $request->user();

        $subject = $this->ownedSubject($data, (int) $user->id);

        $post = DB::transaction(function () use ($request, $data, $user, $subject) {
            $post = FeedPost::create([
                'user_id' => $user->id,
                'is_active' => true,
                'share_count' => 0,
                'title' => $data['title'] ?? null,
                'subject_type' => $subject['type'],
                'subject_id' => $subject['id'],
                'body' => $data['body'],
                'expire_at' => $data['expire_at'] ?? null,
                'image' => $request->hasFile('image')
                    ? $this->uploads->store($request->file('image'))
                    : null,
            ]);

            $this->storeGallery($request, $post);

            return $post;
        });

        $post->load(['user:id,name,logo,image', 'images'])
            ->loadCount(self::counts());

        return response()->json(['success' => true, 'data' => new PostResource($post)], 201);
    }

    /** POST /api/v2/posts/{post} — edit own post (multipart, so not PUT). */
    public function update(Request $request, FeedPost $post)
    {
        $this->authorizeOwner($request, $post);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:191'],
            'body' => ['nullable', 'string'],
            'expire_at' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            // Send subject_type='' (or null) to unlink; send both to relink.
            'subject_type' => ['nullable', 'string', Rule::in($this->subjects->types())],
            'subject_id' => ['nullable', 'required_with:subject_type', 'integer', 'min:1'],
            'image' => ['nullable', ...ImageUploadService::validationRules()],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ImageUploadService::validationRules(),
            // Opt-in: without it, uploading images appends instead of wiping.
            'replace_images' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $data, $post) {
            if ($request->hasFile('image')) {
                $previous = $post->image;
                $post->image = $this->uploads->store($request->file('image'));
                $this->uploads->delete($previous);
            }

            foreach (['title', 'body', 'expire_at', 'is_active'] as $field) {
                if (array_key_exists($field, $data)) {
                    $post->{$field} = $data[$field];
                }
            }

            // Only touched when the caller mentions it, so an edit that says
            // nothing about the link leaves the link alone.
            if (array_key_exists('subject_type', $data)) {
                $subject = $this->ownedSubject($data, (int) $post->user_id);
                $post->subject_type = $subject['type'];
                $post->subject_id = $subject['id'];
            }

            $post->save();

            // v1 wiped the whole gallery on any update that carried images.
            // Here that is explicit, so a client adding one photo does not
            // silently destroy the rest.
            if ($request->boolean('replace_images')) {
                foreach ($post->images as $image) {
                    $this->uploads->delete($image->image);
                    $image->delete();
                }

                $post->unsetRelation('images');
            }

            $this->storeGallery($request, $post);
        });

        $post->load(['user:id,name,logo,image', 'images'])
            ->loadCount(self::counts());

        return response()->json(['success' => true, 'data' => new PostResource($post)]);
    }

    /** DELETE /api/v2/posts/{post} — delete own post and its files. */
    public function destroy(Request $request, FeedPost $post)
    {
        $this->authorizeOwner($request, $post);

        DB::transaction(function () use ($post) {
            foreach ($post->images as $image) {
                $this->uploads->delete($image->image);
                $image->delete();
            }

            $this->uploads->delete($post->image);

            $post->delete();
        });

        return response()->json(['success' => true, 'message' => 'Post deleted.']);
    }

    /** POST /api/v2/posts/{post}/share — count a share. */
    public function share(FeedPost $post)
    {
        $post->increment('share_count');

        return response()->json(['success' => true, 'data' => [
            'id' => (int) $post->id,
            'share_count' => (int) $post->refresh()->share_count,
        ]]);
    }

    /**
     * POST /api/v2/posts/{post}/react — like (1), dislike (-1) or clear (0).
     *
     * The `likes` table has existed all along with no endpoint able to write
     * it; the feed showed counts nobody could change.
     */
    public function react(Request $request, FeedPost $post)
    {
        $data = $request->validate([
            'reaction' => ['required', 'integer', 'in:-1,0,1'],
        ]);

        $user = $request->user();
        $reaction = (int) $data['reaction'];

        $existing = Like::query()
            ->where('post_id', $post->id)
            ->where('user_id', $user->id)
            ->first();

        if ($reaction === 0) {
            $existing?->delete();
        } elseif ($existing) {
            $existing->like = $reaction;
            $existing->save();
        } else {
            $like = new Like(['post_id' => $post->id, 'like' => $reaction]);
            $like->user_id = $user->id;
            $like->save();
        }

        $post->loadCount(['likes', 'dislikes']);

        return response()->json(['success' => true, 'data' => [
            'id' => (int) $post->id,
            'my_reaction' => $reaction === 0 ? null : $reaction,
            'likes_count' => (int) $post->likes_count,
            'dislikes_count' => (int) $post->dislikes_count,
        ]]);
    }

    // ───────────────────────────── internals ─────────────────────────────

    /**
     * The viewer on a PUBLIC route. `$request->user()` resolves the default
     * guard, which is not sanctum, so a bearer token on an unauthenticated
     * route would be ignored and the feed would never personalise. Asking the
     * sanctum guard directly returns the token's owner, or null for a guest.
     */
    private function viewer(Request $request)
    {
        return $request->user() ?: auth('sanctum')->user();
    }

    private function baseQuery(): Builder
    {
        return FeedPost::query()
            ->with(['user:id,name,logo,image,latitude,longitude', 'images'])
            ->withCount(self::counts());
    }

    private function applyOrdering(Builder $query, array $data): void
    {
        $lat = $data['latitude'] ?? null;
        $lng = $data['longitude'] ?? null;

        if ($lat === null || $lng === null) {
            $query->orderByDesc('id');

            return;
        }

        // Nearest-first, computed in SQL. v1 pulled every row into PHP to do
        // this, which is why the guest feed degraded with the table size.
        $query->join('users', 'users.id', '=', 'posts.user_id')
            ->whereNotNull('users.latitude')
            ->whereNotNull('users.longitude')
            ->select('posts.*')
            ->orderByRaw(
                '(6371 * ACOS(LEAST(1, COS(RADIANS(?)) * COS(RADIANS(users.latitude)) '
                .'* COS(RADIANS(users.longitude) - RADIANS(?)) '
                .'+ SIN(RADIANS(?)) * SIN(RADIANS(users.latitude)))))',
                [$lat, $lng, $lat]
            );
    }

    /** @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $posts */
    private function decorate($posts, $user)
    {
        $this->attachViewerState($posts->getCollection(), $user);

        // One query per linked subject type for the whole page, so PostResource
        // can render the tap target without a lookup per row.
        $this->subjects->preload($posts->getCollection());

        return $posts;
    }

    /**
     * One query for the whole page — not one per row, and certainly not one
     * per field per row the way the v1 resource did it.
     */
    private function attachViewerState($posts, $user): void
    {
        $isMine = fn (Post $p) => $user !== null && (int) $p->user_id === (int) $user->id;

        if ($user === null) {
            $posts->each(function (Post $p) {
                $p->my_reaction = null;
                $p->is_mine = false;
            });

            return;
        }

        $reactions = Like::query()
            ->whereIn('post_id', $posts->pluck('id'))
            ->where('user_id', $user->id)
            ->pluck('like', 'post_id');

        $posts->each(function (Post $p) use ($reactions, $isMine) {
            $p->my_reaction = isset($reactions[$p->id]) ? (int) $reactions[$p->id] : null;
            $p->is_mine = $isMine($p);
        });
    }

    private function storeGallery(Request $request, Post $post): void
    {
        if (! $request->hasFile('images')) {
            return;
        }

        foreach ($request->file('images') as $file) {
            if (! $file) {
                continue;
            }

            $image = new Image();
            $image->image = $this->uploads->store($file);
            $post->images()->save($image);
        }
    }

    private function authorizeOwner(Request $request, Post $post): void
    {
        if ((int) $post->user_id !== (int) $request->user()->id) {
            abort(403, 'You can only manage your own posts.');
        }
    }
}
