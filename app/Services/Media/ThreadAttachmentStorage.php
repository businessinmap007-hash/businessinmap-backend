<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Where conversation evidence files live — a PRIVATE directory under
 * storage/app, NOT public/, so a file is never reachable by guessing a URL.
 *
 * Unlike ImageUploadService (which writes to public/ for posts and logos),
 * these are served only through an authenticated controller that checks the
 * requester is a party to the conversation (or the admin/judge). The stored
 * name is random — the recognisable original name is kept in the DB column.
 */
final class ThreadAttachmentStorage
{
    /** Relative to storage/app — outside the web root. */
    public const DIR = 'thread-attachments';

    /** Evidence can be a photo OR a document (a receipt, a contract). */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf'];

    public const MAX_KILOBYTES = 8192;

    /**
     * Validation for an attachments field. `file` (not `image`) so a PDF is
     * allowed, constrained to the vetted list of image + document types.
     *
     * @return list<string>
     */
    public static function validationRules(): array
    {
        return [
            'file',
            'mimes:' . implode(',', self::ALLOWED_EXTENSIONS),
            'max:' . self::MAX_KILOBYTES,
        ];
    }

    public function store(UploadedFile $file): string
    {
        $dir = storage_path('app/' . self::DIR);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));

        if (! in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            $ext = 'jpg';
        }

        $name = time() . '_' . Str::random(20) . '.' . $ext;
        $file->move($dir, $name);

        return self::DIR . '/' . $name;
    }

    /**
     * Absolute path of a stored file, or null if the path escapes the private
     * directory or the file is gone. Guards against a tampered DB path reading
     * an arbitrary file off disk.
     */
    public function absolute(?string $path): ?string
    {
        $path = ltrim((string) $path, '/');

        if ($path === '' || ! str_starts_with($path, self::DIR . '/')) {
            return null;
        }

        $full = realpath(storage_path('app/' . $path));
        $root = realpath(storage_path('app/' . self::DIR));

        if ($full === false || $root === false || ! str_starts_with($full, $root)) {
            return null;
        }

        return is_file($full) ? $full : null;
    }

    public function delete(?string $path): void
    {
        $full = $this->absolute($path);

        if ($full !== null) {
            @unlink($full);
        }
    }
}
