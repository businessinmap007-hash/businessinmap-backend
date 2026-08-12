<?php

namespace App\Console\Commands;

use App\Services\Catalog\OpenFoodFactsMatcher;
use App\Services\Catalog\OpenFoodFactsRow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Attach a real barcode and a real photograph to the products already in the
 * catalog, by matching them against the Open Food Facts export.
 *
 * The 986 rows were written from knowledge and left deliberately without either
 * — «accuracy can't be guaranteed from memory without a verified source». This
 * is the source. Nothing about a product's identity is rewritten: not its name,
 * not its brand, not its department, not its size. Only the two facts it never
 * had, plus a note saying where they came from.
 *
 * Dry by default. `--apply` is the only thing that writes.
 */
class MatchOpenFoodFacts extends Command
{
    protected $signature = 'bim:off-match
        {--file= : The fetcher\'s CSV (defaults to database/seeders/data/catalog/off_egypt_products.csv)}
        {--category= : Limit to one product_categories slug, e.g. grocery_retail}
        {--min-score=0.8 : Name agreement below this is never automatic}
        {--margin=0.2 : How far the best match must beat the runner-up}
        {--report= : Where to write the review sheet (defaults to storage/app/off-match-<date>.csv)}
        {--apply : Write the barcodes and images. Without it nothing is touched}';

    protected $description = 'Match catalog products against Open Food Facts and attach barcodes/images';

    private const REPORT_COLUMNS = [
        'decision', 'score', 'runner_up', 'reason',
        'bim_code', 'name_ar', 'name_en', 'brand', 'size', 'department',
        'off_barcode', 'off_name', 'off_name_ar', 'off_brand', 'off_size', 'off_image',
    ];

    public function handle(): int
    {
        $file = (string) ($this->option('file')
            ?: database_path('seeders/data/catalog/off_egypt_products.csv'));

        if (! is_readable($file)) {
            $this->error("Cannot read {$file} — run bim:off-fetch first.");

            return self::FAILURE;
        }

        $matcher = new OpenFoodFactsMatcher($this->readSource($file));
        $this->line("Source: {$this->sourceCount} rows, {$matcher->brandCount()} brands");

        $products = $this->catalogProducts();
        $this->line("Catalog: {$products->count()} products");
        $this->newLine();

        $report = [];

        // Seeded with what the catalog ALREADY holds, not just what this run
        // hands out. A previous run gave «الرشيدي حلاوة ٣٥٠ جم» the barcode of
        // a source row that states no size; the next run then offered the very
        // same barcode to the ٥٠٠ جم. One barcode is one article, across runs
        // as much as within one.
        $taken = DB::table('catalog_products')
            ->whereNotNull('default_barcode')->where('default_barcode', '!=', '')
            ->pluck('bim_code', 'default_barcode')
            ->all();
        $counts = ['matched' => 0, 'review' => 0, 'none' => 0, 'already' => 0, 'collision' => 0];

        $bar = $this->output->createProgressBar($products->count());

        foreach ($products as $product) {
            $bar->advance();

            if ((string) ($product->default_barcode ?? '') !== '') {
                $counts['already']++;

                continue;
            }

            $size = $product->package_value > 0 && $product->unit_code
                ? OpenFoodFactsRow::normalise((float) $product->package_value, (string) $product->unit_code)
                : null;

            $result = $matcher->match(
                (string) ($product->brand_en ?? ''),
                (string) $product->name_en,
                $size,
                (float) $this->option('min-score'),
                (float) $this->option('margin'),
            );

            $row = $result['row'];

            // A barcode identifies ONE product. If two catalog rows both reach
            // for it, neither may have it — that is a sign the two rows are a
            // duplicate pair, or that the match is wrong, and both are things
            // for a person to look at.
            $decision = 'none';

            if ($row) {
                if (isset($taken[$row->barcode])) {
                    $decision = 'collision';
                    $result['reason'] = 'barcode-already-claimed-by-' . $taken[$row->barcode];
                } else {
                    $decision = 'matched';
                    $taken[$row->barcode] = $product->bim_code;
                }
            } elseif ($result['score'] > 0) {
                $decision = 'review';
            }

            $counts[$decision]++;
            $report[] = $this->reportRow($decision, $product, $result, $size);

            if ($decision === 'matched' && $this->option('apply')) {
                $this->attach($product, $row);
            }
        }

        $bar->finish();
        $this->newLine(2);

        $path = $this->writeReport($report);

        $this->table(
            ['matched', 'review', 'no candidate', 'collision', 'already had one'],
            [[$counts['matched'], $counts['review'], $counts['none'], $counts['collision'], $counts['already']]]
        );

        $this->line("Review sheet → {$path}");

        if (! $this->option('apply')) {
            $this->warn('Nothing was written. Re-run with --apply once the sheet reads right.');
        }

        return self::SUCCESS;
    }

    private int $sourceCount = 0;

    /** @return \Generator<OpenFoodFactsRow> */
    private function readSource(string $file): \Generator
    {
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        if ($header) {
            // The BOM the fetcher writes for Excel is not part of the first
            // column's name.
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        }

        while (($line = fgetcsv($handle)) !== false) {
            if ($header === false || count($line) !== count($header)) {
                continue;
            }

            $this->sourceCount++;

            yield OpenFoodFactsRow::fromCsv(array_combine($header, $line));
        }

        fclose($handle);
    }

    private function catalogProducts(): \Illuminate\Support\Collection
    {
        return DB::table('catalog_products as p')
            ->leftJoin('catalog_brands as b', 'b.id', '=', 'p.brand_id')
            ->leftJoin('catalog_units as u', 'u.id', '=', 'p.unit_id')
            ->join('product_categories as c', 'c.id', '=', 'p.product_category_id')
            ->join('product_category_children as ch', 'ch.id', '=', 'p.product_category_child_id')
            ->whereNull('p.deleted_at')
            ->when($this->option('category'), fn ($q) => $q->where('c.slug', $this->option('category')))
            ->orderBy('p.id')
            ->get([
                'p.id', 'p.bim_code', 'p.name_ar', 'p.name_en', 'p.default_barcode',
                'p.package_value', 'u.code as unit_code',
                'b.name_en as brand_en', 'b.slug as brand_slug',
                'ch.slug as department',
            ]);
    }

    /**
     * The two facts the row never had, and where they came from. Nothing about
     * the product's identity is rewritten — not its name, brand, department or
     * size. Those are the owner's, and the source is not better informed about
     * them than he is.
     */
    private function attach(object $product, OpenFoodFactsRow $row): void
    {
        DB::table('catalog_products')->where('id', $product->id)->update([
            'default_barcode' => $row->barcode,
            // An Egyptian GS1 prefix («622») is the source saying this article
            // is registered here — not a guess about which shelf it sits on.
            'is_verified_egypt' => str_starts_with($row->barcode, '622') ? 1 : 0,
            'verification_source' => 'openfoodfacts:' . $row->source . ' (ODbL)',
            'updated_at' => now(),
        ]);

        if ($row->imageUrl === '') {
            return;
        }

        DB::table('catalog_products')->where('id', $product->id)->update(['main_image' => $row->imageUrl]);

        // The photograph is CC-BY-SA and the attribution is a condition of
        // using it. `catalog_product_images` is where the schema keeps that,
        // so the licence travels with the image rather than living in a commit
        // message nobody reads.
        DB::table('catalog_product_images')->updateOrInsert(
            ['product_id' => $product->id, 'image_path' => $row->imageUrl],
            [
                'image_type' => 'main',
                'is_primary' => 1,
                'sort_order' => 0,
                'source_name' => 'Open Food Facts',
                'source_url' => 'https://world.openfoodfacts.org/product/' . $row->barcode,
                'license_note' => 'CC-BY-SA 3.0 — Open Food Facts contributors',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /** @return array<int,string> */
    private function reportRow(string $decision, object $product, array $result, ?array $size): array
    {
        // The closest candidate, accepted or not — a sheet that only shows
        // accepted matches gives a person nothing to decide about.
        $row = $result['row'] ?? $result['best'];

        $show = fn (?array $s) => $s ? ($s['value'] . ' ' . ($s['type'] === 'weight' ? 'g' : 'ml')) : '';

        return [
            $decision,
            number_format($result['score'], 2),
            number_format($result['runnerUp'], 2),
            $result['reason'],
            (string) $product->bim_code,
            (string) $product->name_ar,
            (string) $product->name_en,
            (string) ($product->brand_en ?? ''),
            $show($size),
            (string) $product->department,
            $row?->barcode ?? '',
            $row?->name ?? '',
            $row?->nameAr ?? '',
            $row?->brand ?? '',
            $show($row?->size()),
            $row?->imageUrl ?? '',
        ];
    }

    /** @param  array<int,array<int,string>>  $rows */
    private function writeReport(array $rows): string
    {
        $path = (string) ($this->option('report')
            ?: storage_path('app/off-match-' . now()->toDateString() . '.csv'));

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
