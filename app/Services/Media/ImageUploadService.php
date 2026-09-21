<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * The single place v2 turns an uploaded file into a stored path.
 *
 * Before this, `Api/V2` had no file handling at all (no hasFile, no store, no
 * move) — v1 expected the client to upload elsewhere and then pass path
 * *strings* into `Image->image`, but the endpoint that produced those strings
 * (`Api\V1\ImageController`) is imported in routes/api.php and never routed,
 * so there was no working upload path in the API.
 *
 * Writes to `public/files/uploads` — the same directory AdminV2 and every
 * legacy row already use — so one post's images render identically in the
 * panel and the app. Paths are stored relative ("files/uploads/x.jpg"); never
 * absolute, or they break the moment the host changes.
 */
final class ImageUploadService
{
    public const PUBLIC_DIR = 'files/uploads';

    /**
     * Private photos live OUTSIDE public/, under storage/app, and are served
     * only by a signed route (see PlanPhotoUrl). A stored path that starts
     * with this prefix is private; anything else is a public upload.
     */
    public const PRIVATE_DIR = 'plan-photos';

    /** Extensions we are willing to write, whatever the client claims. */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public const MAX_KILOBYTES = 8192;

    /**
     * Rules callers should apply to an uploaded image field.
     *
     * Returned as a LIST, not a pipe-delimited string: inside an array-form
     * rule set Laravel treats each element as one whole rule, so a packed
     * "image|mimes:…|max:…" string becomes a rule literally named that and
     * blows up with "Method validateImage|mimes does not exist".
     *
     * @return list<string>
     */
    public static function validationRules(): array
    {
        return [
            'image',
            'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
            'max:'.self::MAX_KILOBYTES,
        ];
    }

    /**
     * Store one upload and return its public-relative path.
     *
     * The client's filename is never reused: it is attacker-controlled and can
     * carry path separators or a second extension. We keep only a sanitised
     * stem for recognisability and derive the extension from the file itself.
     */
    public function store(UploadedFile $file): string
    {
        $dir = public_path(self::PUBLIC_DIR);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $extension = strtolower((string) $file->guessExtension() ?: $file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $extension = 'jpg';
        }

        $stem = Str::of(pathinfo((string) $file->getClientOriginalName(), PATHINFO_FILENAME))
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9_-]+/', '-')
            ->trim('-')
            ->limit(40, '')
            ->value();

        if ($stem === '') {
            $stem = 'image';
        }

        $name = time().'_'.Str::random(8).'_'.$stem.'.'.$extension;

        $file->move($dir, $name);

        return self::PUBLIC_DIR.'/'.$name;
    }

    /**
     * Copy a private file to a new random private name and return the new
     * relative path, or null when the source is missing. Used when one photo is
     * attached to several plans: each gets its own file, so deleting one never
     * breaks another.
     */
    public function copyPrivate(string $relative): ?string
    {
        if (! self::isPrivate($relative)) {
            return null;
        }

        $source = realpath(self::privatePath($relative));
        $root = realpath(self::privatePath(self::PRIVATE_DIR));

        if ($source === false || $root === false || ! str_starts_with($source, $root) || ! is_file($source)) {
            return null;
        }

        $name = Str::random(40).'.'.strtolower(pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg');

        if (! @copy($source, $root.DIRECTORY_SEPARATOR.$name)) {
            return null;
        }

        return self::PRIVATE_DIR.'/'.$name;
    }

    public static function isPrivate(?string $path): bool
    {
        return str_starts_with(ltrim((string) $path, '/'), self::PRIVATE_DIR.'/');
    }

    public static function privatePath(string $relative): string
    {
        return storage_path('app/'.ltrim($relative, '/'));
    }

    /**
     * Store one upload privately and return its relative path
     * (`plan-photos/<random>.<ext>`). The name is random and never derived
     * from the client's, so the path reveals nothing and cannot collide.
     */
    public function storePrivate(UploadedFile $file): string
    {
        $dir = self::privatePath(self::PRIVATE_DIR);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $extension = strtolower((string) $file->guessExtension() ?: $file->getClientOriginalExtension());

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $extension = 'jpg';
        }

        $name = Str::random(40).'.'.$extension;

        $file->move($dir, $name);

        return self::PRIVATE_DIR.'/'.$name;
    }

    /**
     * Delete a stored file. Refuses anything that escapes the upload
     * directory, so a tampered database path cannot unlink arbitrary files.
     */
    public function delete(?string $path): void
    {
        $path = ltrim((string) $path, '/');

        if (self::isPrivate($path)) {
            $full = realpath(self::privatePath($path));
            $root = realpath(self::privatePath(self::PRIVATE_DIR));

            if ($full !== false && $root !== false && str_starts_with($full, $root) && is_file($full)) {
                @unlink($full);
            }

            return;
        }

        if ($path === '' || ! str_starts_with($path, self::PUBLIC_DIR.'/')) {
            return;
        }

        $full = realpath(public_path($path));
        $root = realpath(public_path(self::PUBLIC_DIR));

        if ($full === false || $root === false || ! str_starts_with($full, $root)) {
            return;
        }

        if (is_file($full)) {
            @unlink($full);
        }
    }
}
