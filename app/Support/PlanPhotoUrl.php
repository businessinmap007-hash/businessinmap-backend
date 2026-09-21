<?php

namespace App\Support;

use App\Models\Image;
use App\Models\TrainerPhoto;
use App\Services\Media\ImageUploadService;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * The address of a training-plan photo.
 *
 * Plan photos are private to the trainer and the trainee, so they never sit
 * under `public/` where anyone holding a path could fetch them. They are
 * served by a signed route instead, and the only people who are ever handed a
 * link are the two parties, inside a plan payload they are already entitled
 * to read.
 *
 * The expiry is snapped to a 6-hour boundary rather than "now + 6h", so the
 * same photo has the same URL for hours — a per-request URL would defeat the
 * app's image cache and re-download every photo on every screen open. A link
 * therefore lives between 6 and 12 hours.
 */
final class PlanPhotoUrl
{
    private const WINDOW_SECONDS = 21600;

    public static function for(Image $image): string
    {
        if (! ImageUploadService::isPrivate($image->image)) {
            // A legacy public photo keeps its path; nothing to sign.
            return (string) $image->image;
        }

        return URL::temporarySignedRoute('plan-photos.show', self::expiry(), ['image' => $image->id]);
    }

    /** A trainer's library photo — same signed, snapped-expiry link. */
    public static function forTrainerPhoto(TrainerPhoto $photo): string
    {
        return URL::temporarySignedRoute('trainer-photos.show', self::expiry(), ['photo' => $photo->id]);
    }

    private static function expiry(): Carbon
    {
        return Carbon::createFromTimestamp((intdiv(time(), self::WINDOW_SECONDS) + 2) * self::WINDOW_SECONDS);
    }
}
