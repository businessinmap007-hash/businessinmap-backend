<?php

namespace App\Support\AdminV2;

final class ImagePath
{
    public static function url(?string $path): ?string
    {
        $path = (string)($path ?? '');
        $path = ltrim($path, '/');

        if ($path === '') {
            return null;
        }

        // full URL
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // ✅ enforce legacy uploads folder: files/uploads/...
        if (!str_starts_with($path, 'files/uploads/')) {
            if (str_starts_with($path, 'uploads/')) {
                $path = 'files/' . $path; // uploads/... -> files/uploads/...
            } elseif (!str_contains($path, '/')) {
                $path = 'files/uploads/' . $path; // filename -> files/uploads/filename
            }
        }

        return asset($path);
    }
}
