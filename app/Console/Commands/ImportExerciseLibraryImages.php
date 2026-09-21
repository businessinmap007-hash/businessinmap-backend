<?php

namespace App\Console\Commands;

use App\Models\Image;
use App\Models\LibraryExercise;
use App\Services\Media\ImageUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Attaches demonstration photos to the exercise catalogue from the Free
 * Exercise DB (public domain — https://github.com/yuhonas/free-exercise-db).
 *
 * The mapping (our exercise → their folder) is hand-reviewed and versioned in
 * database/data/exercise_library_image_map.php; the pictures themselves are
 * downloaded, not committed, so the repository stays small and every
 * environment can rebuild the same gallery. Idempotent: an exercise that
 * already has pictures is left alone unless --refresh is given.
 */
class ImportExerciseLibraryImages extends Command
{
    protected $signature = 'exercise-library:import-images
        {--only= : Import just the exercise with this name_en}
        {--map= : Path to an alternative mapping file (defaults to the versioned one)}
        {--refresh : Replace pictures an exercise already has}
        {--dry-run : List what would be downloaded and stop}';

    protected $description = 'Download demonstration photos for the exercise library (public-domain Free Exercise DB)';

    private const BASE = 'https://raw.githubusercontent.com/yuhonas/free-exercise-db/main/exercises/';

    /** The dataset ships two frames per exercise: start and end position. */
    private const FRAMES = [0, 1];

    public function handle(ImageUploadService $uploads): int
    {
        $map = require ($this->option('map') ?: database_path('data/exercise_library_image_map.php'));
        $only = trim((string) $this->option('only'));

        $done = $skipped = $missing = $failed = 0;

        foreach ($map as $nameEn => $folder) {
            if ($only !== '' && strcasecmp($only, $nameEn) !== 0) {
                continue;
            }

            $exercise = LibraryExercise::query()->where('name_en', $nameEn)->first();

            if (! $exercise) {
                $this->warn("no exercise named \"{$nameEn}\" — skipped");
                $missing++;

                continue;
            }

            if ($exercise->images()->exists()) {
                if (! $this->option('refresh')) {
                    $skipped++;

                    continue;
                }

                foreach ($exercise->images as $old) {
                    $uploads->delete($old->image);
                    $old->delete();
                }
            }

            if ($this->option('dry-run')) {
                $this->line("would import {$nameEn} <- {$folder}");

                continue;
            }

            $paths = $this->download($folder, $exercise->id);

            if ($paths === []) {
                $this->warn("could not download {$nameEn} ({$folder})");
                $failed++;

                continue;
            }

            foreach ($paths as $path) {
                Image::create([
                    'image' => $path,
                    'imageable_id' => $exercise->id,
                    'imageable_type' => $exercise->getMorphClass(),
                    'source' => Image::SOURCE_UPLOAD,
                ]);
            }

            $this->line("{$nameEn}: ".count($paths).' photos');
            $done++;
        }

        $this->info("done {$done}, already had photos {$skipped}, unknown exercise {$missing}, failed {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<string> public-relative paths written, or [] if any frame failed
     */
    private function download(string $folder, int $exerciseId): array
    {
        $dir = public_path(ImageUploadService::PUBLIC_DIR.'/exercise-library');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $written = [];

        foreach (self::FRAMES as $frame) {
            $response = Http::timeout(30)->get(self::BASE.rawurlencode($folder)."/{$frame}.jpg");

            // Only ever store something that really is a picture.
            if (! $response->successful() || @getimagesizefromstring($response->body()) === false) {
                foreach ($written as $path) {
                    @unlink(public_path($path));
                }

                return [];
            }

            $relative = ImageUploadService::PUBLIC_DIR."/exercise-library/{$exerciseId}-{$frame}.jpg";
            file_put_contents(public_path($relative), $response->body());
            $written[] = $relative;
        }

        return $written;
    }
}
