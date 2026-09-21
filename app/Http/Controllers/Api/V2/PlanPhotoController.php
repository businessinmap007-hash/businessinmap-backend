<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Models\PlanExercise;
use App\Models\PlanMeal;
use App\Services\Media\ImageUploadService;

/**
 * Streams a training-plan photo from private storage.
 *
 * No login: the link is the credential, signed and time-limited (see
 * PlanPhotoUrl), and it is only ever placed in a plan payload that the
 * trainer or the trainee is already allowed to read. That is what lets a
 * plain image widget load it without carrying a bearer token.
 */
final class PlanPhotoController extends Controller
{
    /** GET /api/v2/plan-photos/{image} — `signed` middleware. */
    public function show(int $image)
    {
        $row = Image::query()
            ->whereKey($image)
            ->whereIn('imageable_type', [PlanExercise::class, PlanMeal::class])
            ->first();

        abort_unless($row && ImageUploadService::isPrivate($row->image), 404);

        $full = ImageUploadService::privatePath($row->image);

        abort_unless(is_file($full), 404);

        return response()->file($full, [
            'Cache-Control' => 'private, max-age=21600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
