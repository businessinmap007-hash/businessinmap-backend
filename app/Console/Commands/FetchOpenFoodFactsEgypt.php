<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Pull the Egypt-tagged products out of Open Food Facts and its sister
 * databases into one CSV.
 *
 * The 986 rows already in `catalog_products` were written from knowledge, and
 * left deliberately without barcodes or images because nothing verified backed
 * them. This is that source: 4,213 food + 236 beauty + 141 general products
 * carrying real EAN-13 barcodes — Egyptian ones under the GS1 `622` prefix —
 * and a photo on about 70% of them.
 *
 * It is **not** a register. Open Food Facts is crowdsourced: brands arrive
 * spelled three ways, quantities as «58-68 g», categories in its own taxonomy,
 * and Arabic names on barely one row in seven. Everything downstream of this
 * file has to treat it as a claim to be matched and checked, never as truth to
 * be written straight in.
 *
 * The CSV lands in the repo on purpose. The grocery batch's generator lived in
 * a scratchpad and cannot be replayed; this one can.
 *
 * Data © Open Food Facts contributors, ODbL. Images CC-BY-SA.
 * @see https://world.openfoodfacts.org/data
 */
class FetchOpenFoodFactsEgypt extends Command
{
    protected $signature = 'bim:off-fetch
        {--source=all : food|beauty|products|all}
        {--out= : Where to write (defaults to database/seeders/data/catalog/off_egypt_products.csv)}
        {--page-size=100 : Rows per request}
        {--sleep=12 : Seconds between requests — the search API throttles hard}
        {--max-pages=0 : Stop after this many pages per source (0 = all)}
        {--from-page=1 : Resume a run that was throttled off}
        {--append : Add to the file instead of starting it over}';

    protected $description = 'Fetch the Egypt-tagged Open Food Facts products into a CSV';

    /** The three sister databases, each with its own host and the same API. */
    private const SOURCES = [
        'food' => 'https://world.openfoodfacts.org',
        'beauty' => 'https://world.openbeautyfacts.org',
        'products' => 'https://world.openproductsfacts.org',
    ];

    /**
     * Asked for by name so the response stays small. `product_name_ar` is
     * requested even though it is nearly always absent — where it exists it is
     * the only Arabic anyone wrote by hand, and worth more than a translation.
     */
    private const FIELDS = [
        'code', 'product_name', 'product_name_ar', 'product_name_en',
        'generic_name', 'brands', 'brands_tags', 'quantity',
        'product_quantity', 'product_quantity_unit',
        'categories_tags', 'image_front_url', 'image_url', 'lang', 'stores',
    ];

    public const COLUMNS = [
        'source', 'barcode', 'name', 'name_ar', 'name_en', 'generic_name',
        'brand', 'brand_slug', 'quantity', 'quantity_value', 'quantity_unit',
        'categories', 'image_url', 'lang', 'stores',
    ];

    public function handle(): int
    {
        $sources = $this->option('source') === 'all'
            ? array_keys(self::SOURCES)
            : [(string) $this->option('source')];

        foreach ($sources as $source) {
            if (! isset(self::SOURCES[$source])) {
                $this->error("Unknown source «{$source}». One of: " . implode(', ', array_keys(self::SOURCES)));

                return self::INVALID;
            }
        }

        $path = (string) ($this->option('out')
            ?: database_path('seeders/data/catalog/off_egypt_products.csv'));

        // One barcode is one product even if two sister databases both claim
        // it — the food and beauty sets overlap on household goods. On a resume
        // the barcodes already written count too, or the file grows twins.
        $append = (bool) $this->option('append') && is_readable($path);
        $seen = $append ? $this->barcodesIn($path) : [];

        $handle = fopen($path, $append ? 'a' : 'w');

        if (! $handle) {
            $this->error("Cannot write {$path}");

            return self::FAILURE;
        }

        if ($append) {
            $this->line(count($seen) . ' rows already in the file — appending');
        } else {
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::COLUMNS);
        }

        $written = 0;

        foreach ($sources as $source) {
            $written += $this->fetchSource($source, $handle, $seen);
        }

        fclose($handle);

        $this->newLine();
        $this->info("{$written} products → {$path}");
        $this->line('Data © Open Food Facts contributors, ODbL — record it in verification_source.');

        return self::SUCCESS;
    }

    /** @param  array<string,true>  $seen */
    private function fetchSource(string $source, $handle, array &$seen): int
    {
        $host = self::SOURCES[$source];
        $pageSize = max(1, (int) $this->option('page-size'));
        $sleep = max(0, (int) $this->option('sleep'));
        $maxPages = (int) $this->option('max-pages');

        $this->newLine();
        $this->line("<info>{$source}</info> — {$host}");

        $page = max(1, (int) $this->option('from-page'));
        $written = 0;
        $total = null;

        while (true) {
            $body = $this->page($host, $page, $pageSize);

            if ($body === null) {
                $this->warn("  page {$page} gave up after retries — stopping this source");

                break;
            }

            $total ??= (int) ($body['count'] ?? 0);
            $products = $body['products'] ?? [];

            if ($products === []) {
                break;
            }

            foreach ($products as $product) {
                $barcode = trim((string) ($product['code'] ?? ''));

                if ($barcode === '' || isset($seen[$barcode])) {
                    continue;
                }

                $seen[$barcode] = true;
                fputcsv($handle, $this->row($source, $product));
                $written++;
            }

            $this->line(sprintf('  page %d — %d rows (%d/%s)', $page, count($products), $written, $total ?? '?'));

            if ($total !== null && $page * $pageSize >= $total) {
                break;
            }

            if ($maxPages > 0 && $page >= $maxPages) {
                break;
            }

            $page++;

            // The search API allows ten requests a minute. Going faster earns a
            // 503, and a 503 mid-run is a hole in the data, not a slow run.
            if ($sleep > 0) {
                sleep($sleep);
            }
        }

        return $written;
    }

    /** @return array<string,mixed>|null */
    private function page(string $host, int $page, int $pageSize): ?array
    {
        $url = $host . '/api/v2/search';

        $query = [
            'countries_tags_en' => 'egypt',
            'page' => $page,
            'page_size' => $pageSize,
            'fields' => implode(',', self::FIELDS),
        ];

        // A 503 here is throttling, not an outage. Backing off in seconds and
        // giving up after twenty of them is how the first run came home with
        // 500 of 4,213 rows; the wait has to be long enough to outlast the
        // window that closed.
        $backoff = [20, 45, 90, 150, 240, 300];

        foreach ($backoff as $attempt => $wait) {
            try {
                $response = Http::withHeaders([
                    // They ask reusers to identify themselves.
                    'User-Agent' => 'BusinessInMap/1.0 (catalog import; https://businessinmap.com)',
                ])->timeout(60)->get($url, $query);

                if ($response->successful()) {
                    return $response->json();
                }

                $this->warn(sprintf('  page %d → HTTP %d, waiting %ds', $page, $response->status(), $wait));
            } catch (\Throwable $e) {
                $this->warn(sprintf('  page %d → %s, waiting %ds', $page, $e->getMessage(), $wait));
            }

            sleep($wait);
        }

        return null;
    }

    /** @return array<string,true> */
    private function barcodesIn(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);
        $column = $header ? array_search('barcode', array_map(
            fn ($h) => preg_replace('/^\xEF\xBB\xBF/', '', (string) $h), $header
        ), true) : false;

        $seen = [];

        if ($column !== false) {
            while (($line = fgetcsv($handle)) !== false) {
                if (isset($line[$column]) && $line[$column] !== '') {
                    $seen[(string) $line[$column]] = true;
                }
            }
        }

        fclose($handle);

        return $seen;
    }

    /**
     * One product, flattened. Nothing is cleaned here — a fetcher that also
     * decides what a quantity means is a fetcher whose output cannot be
     * checked against the source.
     *
     * @param  array<string,mixed>  $product
     * @return array<int,string>
     */
    private function row(string $source, array $product): array
    {
        $tags = $product['brands_tags'] ?? [];

        return [
            $source,
            (string) ($product['code'] ?? ''),
            (string) ($product['product_name'] ?? ''),
            (string) ($product['product_name_ar'] ?? ''),
            (string) ($product['product_name_en'] ?? ''),
            (string) ($product['generic_name'] ?? ''),
            (string) ($product['brands'] ?? ''),
            is_array($tags) ? implode('|', $tags) : (string) $tags,
            (string) ($product['quantity'] ?? ''),
            (string) ($product['product_quantity'] ?? ''),
            (string) ($product['product_quantity_unit'] ?? ''),
            implode('|', (array) ($product['categories_tags'] ?? [])),
            (string) ($product['image_front_url'] ?? $product['image_url'] ?? ''),
            (string) ($product['lang'] ?? ''),
            (string) ($product['stores'] ?? ''),
        ];
    }
}
