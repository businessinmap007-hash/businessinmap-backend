<?php

namespace App\Console\Commands;

use App\Services\Catalog\OpenFoodFactsPlacement;
use App\Services\Catalog\OpenFoodFactsRow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bring genuinely NEW products into the catalog from the Open Food Facts
 * export — the ones the hand-written batches never reached — each arriving
 * with the barcode and photograph those batches could not verify.
 *
 * Five conditions, and a row failing any of them is REPORTED, not shaped into
 * something that passes:
 *
 *  1. a barcode the catalog does not already hold;
 *  2. a name;
 *  3. a brand **the catalog already knows** — the Arabic spelling of a brand is
 *     the owner's decision, not a transliteration this command should invent;
 *  4. a category that maps to one of the 22 departments;
 *  5. an Arabic name every word of which is in `off_terms.php`.
 *
 * Condition 5 is the strict one and it is meant to be: «جبنة feta» looks
 * finished, so nobody ever comes back to fix it.
 *
 * Everything lands as `approval_status = 'pending'`. That does not hide it —
 * `CatalogProduct::scopeActive()` filters `is_active` — but it keeps the
 * approved hand-curated batch distinguishable from a machine's suggestion.
 *
 * Dry by default. `--apply` is the only thing that writes.
 */
class ImportOpenFoodFacts extends Command
{
    protected $signature = 'bim:off-import
        {--file= : The fetcher\'s CSV (defaults to database/seeders/data/catalog/off_egypt_products.csv)}
        {--category=grocery_retail : The product_categories slug to import into}
        {--report= : Where to write the rejects sheet}
        {--limit=0 : Stop after this many new products}
        {--apply : Write the products. Without it nothing is touched}';

    protected $description = 'Import new Egypt products from the Open Food Facts export';

    /**
     * A prefix of its own, so this batch cannot collide with `BIM-SM-*` or
     * `BIM-RT-*` by construction — the lesson that cost the grocery run two
     * products to a stale per-brand counter.
     */
    private const CODE_PREFIX = 'BIM-OFF';

    private const REPORT_COLUMNS = [
        'verdict', 'barcode', 'proposed_name_ar', 'source_name', 'brand', 'department', 'quantity', 'image',
    ];

    public function handle(): int
    {
        $file = (string) ($this->option('file')
            ?: database_path('seeders/data/catalog/off_egypt_products.csv'));

        if (! is_readable($file)) {
            $this->error("Cannot read {$file} — run bim:off-fetch first.");

            return self::FAILURE;
        }

        $category = DB::table('product_categories')->where('slug', $this->option('category'))->first();

        if (! $category) {
            $this->error("No product_categories row with slug «{$this->option('category')}».");

            return self::FAILURE;
        }

        $placement = new OpenFoodFactsPlacement;
        $departments = DB::table('product_category_children')
            ->where('product_category_id', $category->id)
            ->pluck('id', 'slug');

        $brands = $this->knownBrands();

        // Listed brands join the map in BOTH modes, or a dry run would report
        // «unknown-brand» for rows the apply run then imports — and a dry run
        // that does not predict the apply is worth nothing.
        $new = $this->addListedBrands($brands, (bool) $this->option('apply'));

        if ($new > 0) {
            $this->line("{$new} brands from off_brands.php" . ($this->option('apply') ? ' created' : ' (would be created)'));
        }

        $units = DB::table('catalog_units')->pluck('id', 'code');
        $heldBarcodes = DB::table('catalog_products')
            ->whereNotNull('default_barcode')->where('default_barcode', '!=', '')
            ->pluck('default_barcode')->flip();

        $this->line(sprintf(
            'Catalog: %d brands known, %d departments, %d barcodes already held',
            count($brands), $departments->count(), $heldBarcodes->count()
        ));

        $counts = array_fill_keys([
            'imported', 'already-held', 'no-name', 'no-brand', 'unknown-brand',
            'no-department', 'cannot-name-in-arabic', 'duplicate-of-existing',
        ], 0);

        $report = [];
        $seenIdentities = [];
        $limit = (int) $this->option('limit');
        $nextCode = $this->nextCodeNumbers();

        foreach ($this->readSource($file) as $row) {
            if ($limit > 0 && $counts['imported'] >= $limit) {
                break;
            }

            $verdict = null;

            if ($row->barcode === '' || $heldBarcodes->has($row->barcode)) {
                $verdict = 'already-held';
            } elseif ($row->name === '') {
                $verdict = 'no-name';
            } elseif ($row->brand === '') {
                $verdict = 'no-brand';
            }

            $brand = $verdict ? null : ($brands[$row->brandKeyValue()] ?? null);

            if (! $verdict && ! $brand) {
                $verdict = 'unknown-brand';
            }

            $department = $verdict ? null : $placement->department($row);

            if (! $verdict && (! $department || ! $departments->has($department))) {
                $verdict = 'no-department';
            }

            $size = $verdict ? null : $row->size();
            $label = $verdict ? '' : $placement->packageLabel($size);
            $nameAr = $verdict ? null : $placement->arabicName($row, (string) $brand->name_ar, $label);

            if (! $verdict && ! $nameAr) {
                $verdict = 'cannot-name-in-arabic';
            }

            // The gate a clean barcode check does NOT give you: the same
            // product already in the catalog under an older code, with no
            // barcode on it to compare against.
            // …and the same product twice in ONE run. The database check alone
            // reads clean in a dry run and then catches the twin on the apply
            // pass, so the two runs disagreed about the count. A dry run that
            // does not predict the apply is worth nothing.
            $identity = $verdict ? '' : $brand->id . '|' . $nameAr . '|' . $label;

            if (! $verdict && (isset($seenIdentities[$identity])
                || $this->alreadyHave((int) $brand->id, (string) $nameAr, $label))) {
                $verdict = 'duplicate-of-existing';
            }

            if ($verdict) {
                $counts[$verdict]++;

                if ($verdict !== 'already-held') {
                    $report[] = [
                        $verdict, $row->barcode, '', $row->name, $row->brand,
                        $department ?? '', $row->quantity, $row->imageUrl,
                    ];
                }

                continue;
            }

            $counts['imported']++;
            $heldBarcodes->put($row->barcode, true);
            $seenIdentities[$identity] = true;

            // Accepted rows go in the sheet too — the name this command is
            // about to write in Arabic is the thing most worth reading before
            // it is written.
            $report[] = [
                'imported', $row->barcode, $nameAr, $row->name, $row->brand,
                $department, $row->quantity, $row->imageUrl,
            ];

            if ($this->option('apply')) {
                $this->insert($row, $category, (int) $departments[$department], $department, $brand, $nameAr, $label, $size, $placement, $units, $nextCode);
            }
        }

        $this->newLine();

        foreach ($counts as $verdict => $n) {
            $this->line(sprintf('  %-24s %d', $verdict, $n));
        }

        $path = $this->writeReport($report);
        $this->newLine();
        $this->line("Rejects sheet → {$path}");

        if (! $this->option('apply')) {
            $this->warn('Nothing was written. Re-run with --apply.');
        }

        return self::SUCCESS;
    }

    /** @return array<string,object> brand key → brand row */
    private function knownBrands(): array
    {
        $brands = [];

        foreach (DB::table('catalog_brands')->get(['id', 'name_ar', 'name_en', 'slug']) as $brand) {
            foreach ([$brand->name_en, $brand->slug, $brand->name_ar] as $spelling) {
                $key = OpenFoodFactsRow::brandKey((string) $spelling);

                if ($key !== '' && ! isset($brands[$key])) {
                    $brands[$key] = $brand;
                }
            }
        }

        return $brands;
    }

    /**
     * Brands the source names that the catalog lacks, created from the written
     * list in `off_brands.php` — never from the source's own spelling.
     *
     * The Arabic spelling of a brand is a decision, and one hundred products
     * carrying the wrong one is one hundred rows to fix. A brand absent from
     * that file stays refused and shows up in the rejects sheet, which is how
     * the file grows.
     *
     * @param  array<string,object>  $brands
     */
    private function addListedBrands(array &$brands, bool $apply): int
    {
        $listed = require database_path('seeders/data/catalog/off_brands.php');
        $added = 0;

        foreach ($listed as $key => $names) {
            if (isset($brands[$key])) {
                continue;
            }

            $slug = \Illuminate\Support\Str::slug((string) $names['en'], '_') ?: $key;

            $id = $apply
                ? DB::table('catalog_brands')->insertGetId([
                    'slug' => $slug,
                    'name_ar' => $names['ar'],
                    'name_en' => $names['en'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                : 0;

            $brands[$key] = (object) [
                'id' => $id, 'slug' => $slug,
                'name_ar' => $names['ar'], 'name_en' => $names['en'],
            ];

            $added++;
        }

        return $added;
    }

    private function alreadyHave(int $brandId, string $nameAr, string $label): bool
    {
        return DB::table('catalog_products')
            ->where('brand_id', $brandId)
            ->where('name_ar', $nameAr)
            ->where(fn ($q) => $q->where('package_label_ar', $label)->orWhereNull('package_label_ar'))
            ->whereNull('deleted_at')
            ->exists();
    }

    /** @return array<string,int> department slug → next running number */
    private function nextCodeNumbers(): array
    {
        $next = [];

        foreach (DB::table('catalog_products')->where('bim_code', 'like', self::CODE_PREFIX . '-%')->pluck('bim_code') as $code) {
            if (preg_match('/^' . self::CODE_PREFIX . '-(.+)-(\d+)$/', (string) $code, $m)) {
                $next[$m[1]] = max($next[$m[1]] ?? 0, (int) $m[2]);
            }
        }

        return $next;
    }

    private function insert(
        OpenFoodFactsRow $row,
        object $category,
        int $childId,
        string $department,
        object $brand,
        string $nameAr,
        string $label,
        ?array $size,
        OpenFoodFactsPlacement $placement,
        $units,
        array &$nextCode,
    ): void {
        $segment = strtoupper(substr(str_replace('_', '', $department), 0, 4));
        $number = ($nextCode[$segment] = ($nextCode[$segment] ?? 0) + 1);

        $unit = $placement->unitFor($size);

        $id = DB::table('catalog_products')->insertGetId([
            'bim_code' => sprintf('%s-%s-%03d', self::CODE_PREFIX, $segment, $number),
            'product_category_id' => $category->id,
            'product_category_child_id' => $childId,
            'brand_id' => $brand->id,
            'product_type' => 'simple',
            'name_ar' => $nameAr,
            'name_en' => trim($brand->name_en . ' ' . $row->name),
            'default_barcode' => $row->barcode,
            'main_image' => $row->imageUrl ?: null,
            'unit_id' => $unit ? ($units[$unit['code']] ?? null) : null,
            'package_value' => $unit['value'] ?? null,
            'package_label_ar' => $label ?: null,
            'country_code' => 'EG',
            'market_scope' => 'egypt',
            'is_verified_egypt' => str_starts_with($row->barcode, '622') ? 1 : 0,
            'verification_source' => 'openfoodfacts:' . $row->source . ' (ODbL)',
            'is_active' => 1,
            'approval_status' => 'pending',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($row->imageUrl === '') {
            return;
        }

        DB::table('catalog_product_images')->insert([
            'product_id' => $id,
            'image_path' => $row->imageUrl,
            'image_type' => 'main',
            'is_primary' => 1,
            'sort_order' => 0,
            'source_name' => 'Open Food Facts',
            'source_url' => 'https://world.openfoodfacts.org/product/' . $row->barcode,
            'license_note' => 'CC-BY-SA 3.0 — Open Food Facts contributors',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return \Generator<OpenFoodFactsRow> */
    private function readSource(string $file): \Generator
    {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        if ($header) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        }

        while (($line = fgetcsv($handle)) !== false) {
            if ($header === false || count($line) !== count($header)) {
                continue;
            }

            yield OpenFoodFactsRow::fromCsv(array_combine($header, $line));
        }

        fclose($handle);
    }

    /** @param  array<int,array<int,string>>  $rows */
    private function writeReport(array $rows): string
    {
        $path = (string) ($this->option('report')
            ?: storage_path('app/off-import-rejects-' . now()->toDateString() . '.csv'));

        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::REPORT_COLUMNS);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $path;
    }
}
