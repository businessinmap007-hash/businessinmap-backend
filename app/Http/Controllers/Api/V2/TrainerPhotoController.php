<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\TrainerPhoto;
use App\Services\Media\ImageUploadService;
use App\Support\BusinessContext;
use App\Support\PlanPhotoUrl;
use Illuminate\Http\Request;

/**
 * The trainer's private photo library, reusable across clients. Owned by the
 * acting business (a `training` delegate works on the gym's library).
 */
final class TrainerPhotoController extends Controller
{
    private const MAX_PER_REQUEST = 10;

    /** GET /api/v2/business/training/photos */
    public function index(Request $request)
    {
        $photos = TrainerPhoto::query()
            ->where('trainer_id', BusinessContext::id($request))
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'data' => ['photos' => $photos->map(fn (TrainerPhoto $p) => $this->serialize($p))->values()]]);
    }

    /** POST /api/v2/business/training/photos — images[] (+ optional caption). */
    public function store(Request $request)
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:' . self::MAX_PER_REQUEST],
            'images.*' => ImageUploadService::validationRules(),
            'caption' => ['nullable', 'string', 'max:120'],
        ]);

        $trainerId = BusinessContext::id($request);
        $count = count($request->file('images', []));

        if (TrainerPhoto::query()->where('trainer_id', $trainerId)->count() + $count > TrainerPhoto::MAX_PER_TRAINER) {
            return response()->json([
                'success' => false,
                'message' => __('الحد الأقصى :max صورة في مكتبتك.', ['max' => TrainerPhoto::MAX_PER_TRAINER]),
            ], 422);
        }

        $uploads = app(ImageUploadService::class);
        $saved = [];

        foreach ($request->file('images') as $file) {
            $saved[] = TrainerPhoto::create([
                'trainer_id' => $trainerId,
                'image' => $uploads->storePrivate($file),
                'caption' => $data['caption'] ?? null,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => ['photos' => array_map(fn (TrainerPhoto $p) => $this->serialize($p), $saved)],
        ], 201);
    }

    /**
     * DELETE /api/v2/business/training/photos/{photo}. Plans that already use
     * a copy of it are unaffected — attaching copies the file.
     */
    public function destroy(Request $request, int $photo)
    {
        TrainerPhoto::query()
            ->where('trainer_id', BusinessContext::id($request))
            ->findOrFail($photo)
            ->delete();

        return response()->json(['success' => true]);
    }

    /** GET /api/v2/trainer-photos/{photo} — `signed` middleware, like plan photos. */
    public function show(int $photo)
    {
        $row = TrainerPhoto::query()->find($photo);

        abort_unless($row && ImageUploadService::isPrivate($row->image), 404);

        $full = ImageUploadService::privatePath($row->image);

        abort_unless(is_file($full), 404);

        return response()->file($full, [
            'Cache-Control' => 'private, max-age=21600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function serialize(TrainerPhoto $p): array
    {
        return [
            'id' => (int) $p->id,
            'caption' => $p->caption,
            'image' => PlanPhotoUrl::forTrainerPhoto($p),
        ];
    }
}
