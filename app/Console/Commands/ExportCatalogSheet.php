<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The catalog as the owner asked to read it:
 *
 *   «الشركة المصنعة او العلامة التجارية — جهينة
 *    لبن كامل الدسم وفى الحجم 1.5 و 1 لتر
 *    جبنة كريمى 200 جرام و 500 جرام
 *    يبقى المطلوب الاسم التجارى والنوع والحجم فقط»
 *
 * One line per **brand + type**, with every size that type is sold in gathered
 * onto it. The catalog stores a row per size — that is the design, since two
 * sizes are two things a merchant prices and stocks separately — but a person
 * reading the catalog wants to see the type once and its sizes beside it.
 *
 * Three columns and nothing else, because those are the three facts:
 * `brand_id`, `short_name_ar`, and the sizes from `package_label_ar`.
 *
 * A row with no type recorded falls back to its full name minus the brand, so
 * the hand-written batches — which predate `short_name_ar` — still appear.
 */
class ExportCatalogSheet extends Command
{
    protected $signature = 'bim:catalog-sheet
        {file? : Where to write (defaults to storage/app/catalog-sheet-<date>.csv)}
        {--category=grocery_retail : Limit to one product_categories slug}
        {--department= : Limit to one department slug}
        {--missing-size : Only types that have a row with no size recorded}';

    protected $description = 'Export the catalog as brand + type + sizes';

    public const COLUMNS = ['العلامة التجارية', 'النوع', 'الأحجام', 'عدد الأصناف', 'باركود', 'القسم'];

    public function handle(): int
    {
        $path = (string) ($this->argument('file')
            ?: storage_path('app/catalog-sheet-' . now()->toDateString() . '.csv'));

        $rows = DB::table('catalog_products as p')
            ->leftJoin('catalog_brands as b', 'b.id', '=', 'p.brand_id')
            ->join('product_category_children as ch', 'ch.id', '=', 'p.product_category_child_id')
            ->join('product_categories as c', 'c.id', '=', 'p.product_category_id')
            ->whereNull('p.deleted_at')
            ->when($this->option('category'), fn ($q) => $q->where('c.slug', $this->option('category')))
            ->when($this->option('department'), fn ($q) => $q->where('ch.slug', $this->option('department')))
            ->orderBy('b.name_ar')->orderBy('ch.slug')->orderBy('p.package_value')
            ->get([
                'p.id', 'p.name_ar', 'p.short_name_ar', 'p.package_label_ar',
                'p.default_barcode', 'b.name_ar as brand_ar', 'ch.slug as department',
            ]);

        $grouped = [];

        foreach ($rows as $row) {
            $brand = trim((string) ($row->brand_ar ?? '')) ?: '—';
            $type = $this->typeOf($row, $brand);
            $key = $brand . '|' . $type . '|' . $row->department;

            $grouped[$key] ??= [
                'brand' => $brand,
                'type' => $type,
                'department' => $row->department,
                'sizes' => [],
                'count' => 0,
                'barcodes' => 0,
            ];

            $size = trim((string) ($row->package_label_ar ?? ''));

            if ($size !== '' && ! in_array($size, $grouped[$key]['sizes'], true)) {
                $grouped[$key]['sizes'][] = $size;
            }

            $grouped[$key]['count']++;
            $grouped[$key]['barcodes'] += trim((string) ($row->default_barcode ?? '')) !== '' ? 1 : 0;
        }

        if ($this->option('missing-size')) {
            $grouped = array_filter($grouped, fn ($g) => $g['sizes'] === [] || count($g['sizes']) < $g['count']);
        }

        $handle = fopen($path, 'w');

        if (! $handle) {
            $this->error("Cannot write {$path}");

            return self::FAILURE;
        }

        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::COLUMNS);

        foreach ($grouped as $group) {
            fputcsv($handle, [
                $group['brand'],
                $group['type'],
                implode(' / ', $group['sizes']),
                $group['count'],
                $group['barcodes'],
                $group['department'],
            ]);
        }

        fclose($handle);

        $this->table(
            ['brand+type lines', 'product rows', 'with a barcode'],
            [[
                count($grouped),
                array_sum(array_column($grouped, 'count')),
                array_sum(array_column($grouped, 'barcodes')),
            ]]
        );

        $this->line("→ {$path}");

        return self::SUCCESS;
    }

    /**
     * The type. Imported rows record it; the hand-written batches predate the
     * column, so their type is recovered by taking the brand off the front of
     * the name and the size off the end.
     */
    private function typeOf(object $row, string $brand): string
    {
        $type = trim((string) ($row->short_name_ar ?? ''));

        if ($type !== '') {
            return $type;
        }

        $name = trim((string) $row->name_ar);
        $size = trim((string) ($row->package_label_ar ?? ''));

        if ($brand !== '—' && str_starts_with($name, $brand)) {
            $name = trim(substr($name, strlen($brand)));
        }

        if ($size !== '' && str_ends_with($name, $size)) {
            $name = trim(substr($name, 0, -strlen($size)));
        }

        return $name;
    }
}
