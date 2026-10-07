<?php

namespace App\Support;

use App\Models\Image;
use App\Services\Media\ImageUploadService;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * The address of a file of an investigation order — a patient's photo of a paper request, or a result the centre
 * attached. Private like the training-plan photos: a signed, time-limited link placed only inside a payload that a party
 * of the order is already allowed to read, with the expiry snapped to a boundary so the app's image cache keeps working.
 */
final class InvestigationFileUrl
{
    private const WINDOW_SECONDS = 21600;

    public static function for(Image $image): string
    {
        if (! ImageUploadService::isPrivate($image->image)) {
            return (string) $image->image;
        }

        $expiry = Carbon::createFromTimestamp((intdiv(time(), self::WINDOW_SECONDS) + 2) * self::WINDOW_SECONDS);

        return URL::temporarySignedRoute('investigation-files.show', $expiry, ['image' => $image->id]);
    }
}
