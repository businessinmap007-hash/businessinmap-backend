<?php

namespace App\Console\Commands;

use App\Models\Sponsor;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class PurgeSponsors extends Command
{
    /**
     * --all      : يحذف كل إعلانات sponsors
     * --expired  : يحذف الإعلانات المنتهية فقط
     * --dry-run  : يعرض ما سيتم حذفه بدون تنفيذ
     */
    protected $signature = 'sponsors:purge {--all} {--expired} {--dry-run}';
    protected $description = 'Delete sponsors and their related images (DB + files)';

    public function handle(): int
    {
        $all     = (bool) $this->option('all');
        $expired = (bool) $this->option('expired');
        $dryRun  = (bool) $this->option('dry-run');

        if (!$all && !$expired) {
            $this->error("Choose one option: --all OR --expired (optional: --dry-run)");
            $this->line("Examples:");
            $this->line("  php artisan sponsors:purge --expired");
            $this->line("  php artisan sponsors:purge --all");
            $this->line("  php artisan sponsors:purge --all --dry-run");
            return self::FAILURE;
        }

        $query = Sponsor::query();

        if ($expired) {
            $query->whereNotNull('expire_at')
                ->where('expire_at', '<', Carbon::now());
        }

        $sponsors = $query->get(['id', 'image', 'expire_at']);

        if ($sponsors->isEmpty()) {
            $this->info("No sponsors found for the selected criteria.");
            return self::SUCCESS;
        }

        // Detect if images table exists (in case you renamed/removed it)
        $hasImagesTable = Schema::hasTable('images');

        // Detect real image column name in images table (no assumptions)
        $imageColumn = $hasImagesTable ? $this->detectImageColumn() : null;

        // Read related morph images in one query (only existing column)
        $morphImages = collect();
        if ($hasImagesTable && $imageColumn) {
            $morphImages = DB::table('images')
                ->where('imageable_type', 'App\\Models\\Sponsor')
                ->whereIn('imageable_id', $sponsors->pluck('id')->all())
                ->select('id', 'imageable_id', $imageColumn)
                ->get()
                ->map(function ($row) use ($imageColumn) {
                    $row->file_value = $row->{$imageColumn} ?? null;
                    return $row;
                });
        }

        $this->line("Sponsors to delete: " . $sponsors->count());
        $this->line("Morph images rows to delete: " . $morphImages->count());
        $this->line("Images column detected: " . ($imageColumn ?? 'NONE'));
        $this->line("Mode: " . ($dryRun ? "DRY RUN (no changes)" : "EXECUTE"));

        if ($dryRun) {
            $this->info("Dry run completed. No deletions were made.");
            return self::SUCCESS;
        }

        DB::beginTransaction();
        try {
            // 1) Delete files referenced by sponsors.image (if exists)
            $deletedFiles = 0;
            foreach ($sponsors as $sponsor) {
                $deletedFiles += $this->deleteFileIfExists($sponsor->image);
            }

            // 2) Delete files referenced by images table (detected column)
            foreach ($morphImages as $img) {
                $deletedFiles += $this->deleteFileIfExists($img->file_value ?? null);
            }

            // 3) Delete images table rows (DB)
            if ($hasImagesTable && $morphImages->isNotEmpty()) {
                DB::table('images')->whereIn('id', $morphImages->pluck('id')->all())->delete();
            }

            // 4) Delete sponsors rows (DB)
            Sponsor::whereIn('id', $sponsors->pluck('id')->all())->delete();

            DB::commit();

            $this->info("Deleted sponsors: " . $sponsors->count());
            $this->info("Deleted image rows: " . $morphImages->count());
            $this->info("Deleted files from disk (found & removed): " . $deletedFiles);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Failed: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * Detect the correct column that stores the file path in `images` table.
     */
    private function detectImageColumn(): ?string
    {
        $candidates = ['path', 'image', 'url', 'file', 'src', 'name'];

        foreach ($candidates as $col) {
            if (Schema::hasColumn('images', $col)) {
                return $col;
            }
        }

        return null;
    }

    /**
     * Delete a file if the given path points to a local file.
     * Supports:
     *  - null/empty
     *  - full URLs (ignored)
     *  - absolute paths
     *  - relative paths like "files/uploads/x.jpg" or "/files/uploads/x.jpg"
     */
    private function deleteFileIfExists(?string $value): int
    {
        if (!$value) return 0;

        $value = trim($value);

        // Ignore URLs
        if (preg_match('#^https?://#i', $value)) {
            return 0;
        }

        // Normalize slashes
        $value = str_replace('\\', '/', $value);

        // If relative, try under public_path first
        $candidates = [];

        // absolute path
        if (str_starts_with($value, '/') || preg_match('#^[A-Za-z]:/#', $value)) {
            $candidates[] = $value;
        } else {
            $candidates[] = public_path($value);
            $candidates[] = public_path('/' . $value);
            // some projects store in storage/app/public and symlink it
            $candidates[] = storage_path('app/public/' . ltrim($value, '/'));
        }

        foreach ($candidates as $filePath) {
            if ($filePath && File::exists($filePath) && File::isFile($filePath)) {
                File::delete($filePath);
                return 1;
            }
        }

        return 0;
    }
}
