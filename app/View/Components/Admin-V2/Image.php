<?php

namespace App\View\Components\AdminV2;

use Illuminate\View\Component;
use Illuminate\Support\Str;

class Image extends Component
{
    public ?string $src = null;

    public function __construct(
        public ?string $path = null,
        public string $alt = '',
        public int $size = 48,
        public string $fit = 'cover',
        public string $radius = '12px',
        public bool $circle = false,
        public string $bg = '#f3f4f6',
        public string $border = '1px solid var(--border)',
        public string $placeholder = 'No image'
    ) {
        $this->src = $this->buildSrc($this->path);
    }

    private function buildSrc(?string $path): ?string
    {
        $path = (string)($path ?? '');
        $path = trim($path);

        if ($path === '') return null;

        // normalize slashes
        $path = str_replace('\\', '/', $path);

        // full URL
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        // remove leading slash
        $path = ltrim($path, '/');

        // if someone stored full filesystem path, cut it to public/
        if (Str::contains($path, 'public/')) {
            $path = Str::after($path, 'public/');
        }

        // ✅ 1) if path exists as-is in public, use it
        if (file_exists(public_path($path))) {
            return asset($path);
        }

        // ✅ 2) try legacy "files/uploads/"
        if (!Str::startsWith($path, 'files/uploads/')) {
            $try = 'files/uploads/' . basename($path);
            if (file_exists(public_path($try))) {
                return asset($try);
            }
        }

        // ✅ 3) try "uploads/"
        if (!Str::startsWith($path, 'uploads/')) {
            $try = 'uploads/' . basename($path);
            if (file_exists(public_path($try))) {
                return asset($try);
            }
        }

        // ✅ 4) try storage (requires php artisan storage:link)
        $try = 'storage/' . ltrim($path, '/');
        if (file_exists(public_path($try))) {
            return asset($try);
        }

        // no file found
        return null;
    }
    public function imageable(){ return $this->morphTo(); }


    public function render()
    {
        return view('components.admin-v2.image');
    }
}
