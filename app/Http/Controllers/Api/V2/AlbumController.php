<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Album;
use App\Models\Image;
use App\Services\Media\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A signed-in account's OWN photo albums — same privacy shape as
 * ProfileController: no id parameter picks whose account, and every
 * album/photo route re-checks ownership itself since Laravel's implicit
 * route-model-binding resolves ANY album id, not just the caller's.
 *
 * v1 had `Album` (title/description + a cover `image` + a morphMany
 * gallery via the generic `Image` model) with no v2 controller at all —
 * AccountResource's own docblock calls that out explicitly. This is the
 * first v2 surface for it.
 */
final class AlbumController extends Controller
{
    public function __construct(private readonly ImageUploadService $uploads)
    {
    }

    /** GET /api/v2/profile/albums */
    public function index(Request $request)
    {
        $albums = $request->user()->albums()->withCount('images')->latest('id')->get();

        return response()->json([
            'success' => true,
            'data' => $albums->map(fn (Album $album) => $this->summary($album))->values(),
        ]);
    }

    /** POST /api/v2/profile/albums */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title_ar' => ['required', 'string', 'max:191'],
            'title_en' => ['nullable', 'string', 'max:191'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
        ]);

        $album = $request->user()->albums()->create($data);

        return response()->json(['success' => true, 'data' => $this->detail($album)], 201);
    }

    /** GET /api/v2/profile/albums/{album} */
    public function show(Request $request, Album $album)
    {
        $this->authorizeOwn($request, $album);

        return response()->json(['success' => true, 'data' => $this->detail($album->load('images'))]);
    }

    /** PATCH /api/v2/profile/albums/{album} */
    public function update(Request $request, Album $album)
    {
        $this->authorizeOwn($request, $album);

        $data = $request->validate([
            'title_ar' => ['sometimes', 'string', 'max:191'],
            'title_en' => ['sometimes', 'nullable', 'string', 'max:191'],
            'description_ar' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'description_en' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        if (! empty($data)) {
            $album->fill($data)->save();
        }

        return response()->json(['success' => true, 'data' => $this->detail($album->fresh('images'))]);
    }

    /**
     * DELETE /api/v2/profile/albums/{album}
     *
     * Every photo's file is unlinked by hand before the rows go: a mass
     * delete on the relation (or the album row itself) fires no model
     * events, so nothing else would ever clean these files up — the same
     * "owned gallery" shape already hit elsewhere in this codebase.
     */
    public function destroy(Request $request, Album $album)
    {
        $this->authorizeOwn($request, $album);

        foreach ($album->images as $image) {
            $this->uploads->delete($image->image);
            $image->delete();
        }
        $this->uploads->delete($album->image);
        $album->delete();

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/v2/profile/albums/{album}/photos
     *
     * `source` mirrors Image::SOURCE_CAMERA/SOURCE_UPLOAD — the same
     * camera-vs-gallery provenance signal already tracked for post images,
     * now available here too as one proof-of-ownership input.
     */
    public function addPhoto(Request $request, Album $album)
    {
        $this->authorizeOwn($request, $album);

        $data = $request->validate([
            'image' => ['required', ...ImageUploadService::validationRules()],
            'source' => ['sometimes', Rule::in([Image::SOURCE_CAMERA, Image::SOURCE_UPLOAD])],
        ]);

        $path = $this->uploads->store($request->file('image'));

        $album->images()->create([
            'image' => $path,
            'source' => $data['source'] ?? Image::SOURCE_UPLOAD,
        ]);

        // The album's cover follows its first photo, so a fresh album isn't
        // a title with nothing to show for it.
        if (! $album->image) {
            $album->update(['image' => $path]);
        }

        return response()->json(['success' => true, 'data' => $this->detail($album->fresh('images'))], 201);
    }

    /** DELETE /api/v2/profile/albums/{album}/photos/{photo} */
    public function removePhoto(Request $request, Album $album, Image $photo)
    {
        $this->authorizeOwn($request, $album);
        abort_unless(
            $photo->imageable_type === Album::class && (int) $photo->imageable_id === (int) $album->id,
            404
        );

        $this->uploads->delete($photo->image);
        $wasCover = $photo->image === $album->image;
        $photo->delete();

        if ($wasCover) {
            $next = $album->images()->first();
            $album->update(['image' => $next?->image]);
        }

        return response()->json(['success' => true, 'data' => $this->detail($album->fresh('images'))]);
    }

    private function authorizeOwn(Request $request, Album $album): void
    {
        abort_unless((int) $album->user_id === (int) $request->user()->id, 403);
    }

    private function summary(Album $album): array
    {
        return [
            'id' => (int) $album->id,
            'title' => $album->title,
            'description' => $album->description,
            'cover' => $album->image ?: null,
            'photos_count' => (int) ($album->images_count ?? $album->images()->count()),
        ];
    }

    private function detail(Album $album): array
    {
        return array_merge($this->summary($album), [
            'photos' => $album->images->map(fn (Image $image) => [
                'id' => (int) $image->id,
                'image' => $image->image,
                'source' => $image->source,
            ])->values(),
        ]);
    }
}
