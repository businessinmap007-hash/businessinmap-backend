<?php

namespace Tests\Feature;

use App\Console\Commands\ExportCatalogSheet;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «الشركة المصنعة او العلامة التجارية — جهينة / لبن كامل الدسم وفى الحجم 1.5 و
 * 1 لتر / جبنة كريمى 200 جرام و 500 جرام / يبقى المطلوب الاسم التجارى والنوع
 * والحجم فقط».
 *
 * The catalog stores a row per SIZE, and that stays right: two sizes are two
 * things a merchant prices and stocks apart. But a person reading it wants the
 * type once with its sizes beside it, and this is that view — brand, type, and
 * the sizes gathered.
 */
class CatalogSheetTest extends TestCase
{
    use DatabaseTransactions;

    private function sheet(array $options = []): array
    {
        $path = tempnam(sys_get_temp_dir(), 'sheet') . '.csv';

        $this->artisan('bim:catalog-sheet', ['file' => $path] + $options)->assertSuccessful();

        // The BOM sits BEFORE the first field's opening quote, so a parser
        // reading the line as-is never recognises that field as quoted and
        // hands back «"العلامة التجارية"» with the quotes still on it. Take the
        // BOM off the content, not off the parsed value.
        $content = preg_replace('/^\xEF\xBB\xBF/', '', trim(file_get_contents($path)));

        $lines = array_map('str_getcsv', array_filter(explode("\n", $content)));
        $header = array_shift($lines);

        @unlink($path);

        return array_map(fn ($line) => array_combine($header, $line), $lines);
    }

    /** Three facts and nothing else. */
    public function test_the_sheet_is_brand_type_and_sizes(): void
    {
        $rows = $this->sheet();

        $this->assertNotEmpty($rows);
        $this->assertSame(ExportCatalogSheet::COLUMNS, array_keys($rows[0]));
    }

    /**
     * The point of the whole thing: one type, its sizes gathered onto one
     * line. Built from the catalog's own «لبن كامل الدسم» rows rather than a
     * fixture, so what is proved is that the real data groups.
     */
    public function test_a_type_sold_in_two_sizes_is_one_line_carrying_both(): void
    {
        // Inside the sheet's own default scope — a bed-sheet set under
        // home_furnishings is a real two-size type and is not on this sheet.
        $type = DB::table('catalog_products as p')
            ->join('product_categories as c', 'c.id', '=', 'p.product_category_id')
            ->where('c.slug', 'grocery_retail')
            ->whereNotNull('short_name_ar')->where('short_name_ar', '!=', '')
            ->whereNotNull('package_label_ar')->where('package_label_ar', '!=', '')
            // Grouped by the department too, because that is what the sheet
            // groups by — a type sold under two departments is two lines, and
            // asking a different question here would fail for the right reason
            // in the wrong place.
            ->select('brand_id', 'short_name_ar', DB::raw('COUNT(DISTINCT package_label_ar) as sizes'))
            ->groupBy('brand_id', 'short_name_ar', 'product_category_child_id')
            ->having('sizes', '>', 1)
            ->first();

        if (! $type) {
            $this->markTestSkipped('No type is stocked in more than one size yet.');
        }

        $brand = (string) DB::table('catalog_brands')->where('id', $type->brand_id)->value('name_ar');

        $line = collect($this->sheet())
            ->first(fn ($row) => $row['العلامة التجارية'] === $brand && $row['النوع'] === $type->short_name_ar);

        $this->assertNotNull($line, "«{$brand} {$type->short_name_ar}» is missing from the sheet");
        $this->assertGreaterThanOrEqual(
            2,
            count(array_filter(explode('/', $line['الأحجام']))),
            'the sizes were not gathered onto the one type'
        );
    }

    /** A type must never be split across lines by anything but its brand. */
    public function test_one_brand_and_type_appear_once_per_department(): void
    {
        $seen = [];

        foreach ($this->sheet() as $row) {
            $key = $row['العلامة التجارية'] . '|' . $row['النوع'] . '|' . $row['القسم'];

            $this->assertArrayNotHasKey($key, $seen, "«{$key}» is on two lines");
            $seen[$key] = true;
        }

        $this->assertNotEmpty($seen);
    }

    /**
     * A row that records no type of its own still yields a clean one.
     *
     * Every row in the catalog records one today — the 62 that did not were
     * backfilled — but the fallback is what lets a batch written before the
     * column appear on this sheet at all, so it is exercised deliberately: a
     * row is stripped of its type inside the transaction, and rolled back.
     */
    public function test_a_row_with_no_recorded_type_still_yields_a_clean_one(): void
    {
        $row = DB::table('catalog_products as p')
            ->join('catalog_brands as b', 'b.id', '=', 'p.brand_id')
            ->join('product_categories as c', 'c.id', '=', 'p.product_category_id')
            ->where('c.slug', 'grocery_retail')
            ->whereNotNull('p.package_label_ar')->where('p.package_label_ar', '!=', '')
            ->first(['p.id', 'p.name_ar', 'p.package_label_ar', 'b.name_ar as brand_ar']);

        $this->assertNotNull($row, 'the grocery catalog is empty');

        DB::table('catalog_products')->where('id', $row->id)->update(['short_name_ar' => null]);

        $line = collect($this->sheet())
            ->first(fn ($sheet) => $sheet['العلامة التجارية'] === $row->brand_ar
                && $sheet['النوع'] !== ''
                && str_contains($row->name_ar, $sheet['النوع']));

        $this->assertNotNull($line, "no line recovered a type for «{$row->name_ar}»");
        $this->assertStringStartsNotWith($row->brand_ar, $line['النوع'], 'the brand is still in the type');
        $this->assertStringEndsNotWith($row->package_label_ar, $line['النوع'], 'the size is still in the type');
    }

    /** Narrowing to one department must not change how anything is grouped. */
    public function test_a_department_filter_narrows_without_regrouping(): void
    {
        $department = (string) DB::table('catalog_products as p')
            ->join('product_category_children as ch', 'ch.id', '=', 'p.product_category_child_id')
            ->where('p.bim_code', 'like', 'BIM-OFF-%')
            ->value('ch.slug');

        if ($department === '') {
            $this->markTestSkipped('Nothing imported yet.');
        }

        $narrowed = $this->sheet(['--department' => $department]);

        $this->assertNotEmpty($narrowed);

        foreach ($narrowed as $row) {
            $this->assertSame($department, $row['القسم']);
        }
    }
}
