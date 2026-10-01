<?php

namespace App\Console\Commands;

use App\Services\Media\ImageUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Downloads the device catalog's open-licensed photos to this server.
 *
 * «حمل كل صور الموديلات الموجودة» — المالك، 2026-10-01. The photos come from
 * Wikimedia Commons (CC BY / CC BY-SA / CC0 / public domain); the hand-reviewed
 * list of which file belongs to which model, and the credit each licence asks
 * for, is versioned in database/seeders/data/mobile_device_images.php. The
 * files themselves are downloaded, not committed — the same arrangement as
 * `exercise-library:import-images` — so every environment rebuilds the same
 * gallery and a hotlinked image never depends on Wikimedia being reachable
 * from the customer's phone.
 *
 * Idempotent: a model whose photo is already local is skipped unless
 * --refresh. A photo an admin uploaded by hand is never replaced.
 */
class ImportDeviceCatalogImages extends Command
{
    protected $signature = 'catalog:import-device-images
        {--refresh : Download again even when the local copy exists}
        {--dry-run : List what would be downloaded and stop}';

    protected $description = 'Download the device catalog\'s open-licensed photos (Wikimedia Commons) to public storage';

    private const DIR = 'catalog-devices';

    private const USER_AGENT = 'BIM-catalog-image-import/1.0 (open-licensed product images)';

    public function handle(): int
    {
        $map = require database_path('seeders/data/mobile_device_images.php');
        $dir = public_path(ImageUploadService::PUBLIC_DIR . '/' . self::DIR);

        if (! is_dir($dir) && ! $this->option('dry-run')) {
            mkdir($dir, 0755, true);
        }

        $done = $skipped = $failed = $missing = 0;

        foreach ($map as $nameEn => [$url, $credit]) {
            $product = DB::table('catalog_products')
                ->where('name_en', $nameEn)
                ->whereNull('deleted_at')
                ->first(['id', 'bim_code', 'main_image']);

            if (! $product) {
                $missing++;

                continue;
            }

            $current = (string) $product->main_image;
            $local = str_starts_with($current, ImageUploadService::PUBLIC_DIR . '/' . self::DIR . '/');
            $ours = $current === '' || $local || str_contains($current, 'wikimedia.org/');

            // Somebody else's photo (an admin upload) — never ours to replace.
            if (! $ours || ($local && is_file(public_path($current)) && ! $this->option('refresh'))) {
                $skipped++;

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("would download {$nameEn}");

                continue;
            }

            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(30)->retry(3, 5000, throw: false)->get($url);
            $size = $response->successful() ? @getimagesizefromstring($response->body()) : false;

            // Only ever store something that really is a picture.
            if ($size === false) {
                $this->warn("could not download {$nameEn} ({$response->status()})");
                $failed++;

                continue;
            }

            $ext = image_type_to_extension($size[2], false) ?: 'jpg';
            $ext = $ext === 'jpeg' ? 'jpg' : $ext;
            $name = preg_replace('/[^A-Za-z0-9-]+/', '-', (string) ($product->bim_code ?: $product->id));
            $relative = ImageUploadService::PUBLIC_DIR . '/' . self::DIR . "/{$name}.{$ext}";

            file_put_contents(public_path($relative), $response->body());

            DB::table('catalog_products')->where('id', $product->id)->update([
                'main_image' => $relative,
                'main_image_credit' => $credit,
                'updated_at' => now(),
            ]);

            $done++;
            usleep(500_000); // be gentle with Wikimedia's servers
        }

        $this->info("downloaded {$done}, already local {$skipped}, unknown model {$missing}, failed {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
