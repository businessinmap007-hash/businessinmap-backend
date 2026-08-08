<?php

namespace App\Models\Concerns;

use App\Models\Image;
use App\Services\Media\ImageUploadService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A gallery the row OWNS: deleting the row deletes the photos, rows and files.
 *
 * The `images` table is polymorphic and shared, which makes attaching photos
 * easy and orphaning them just as easy — a deleted parent leaves rows pointing
 * at pictures nobody can reach, and FILES on disk with nothing left that could
 * ever find them to remove.
 *
 * Booting it here rather than in a controller is the point. A menu item is
 * deleted from the app, from the business panel and from the admin panel; a
 * plan exercise is deleted by its trainer and would be by a cascading plan. A
 * cleanup rule that lives in one of those places is not a rule.
 *
 * **A mass `->delete()` on a query bypasses this**, as Eloquent events always
 * do. Fetch the rows and delete them one by one when they own images.
 */
trait HasOwnedImages
{
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')->orderBy('id');
    }

    /** @return array<int,array{id:int,image:string}> */
    public function imagePayload(): array
    {
        return $this->images->map(fn (Image $image) => [
            'id' => (int) $image->id,
            'image' => $image->image,
        ])->values()->all();
    }

    public static function bootHasOwnedImages(): void
    {
        static::deleting(function (Model $model) {
            $uploads = app(ImageUploadService::class);

            foreach ($model->images as $image) {
                $uploads->delete($image->image);
                $image->delete();
            }
        });
    }
}
