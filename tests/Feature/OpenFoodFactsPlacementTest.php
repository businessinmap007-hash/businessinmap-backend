<?php

namespace Tests\Feature;

use App\Services\Catalog\OpenFoodFactsPlacement;
use App\Services\Catalog\OpenFoodFactsRow;
use Tests\TestCase;

/**
 * Before an Open Food Facts row can become a product it has to be put on a
 * shelf and given an Arabic name. Both answers are refusable, and refusing is
 * the ordinary outcome: two Egypt rows in three carry no category at all.
 *
 * The Arabic is mine — the owner chose «أترجم أنا الاسم» — so `off_terms.php`
 * lists every word this will ever write, and a name with one unknown word is
 * not written at all. «جبنة feta» looks finished, so nobody comes back to it.
 *
 * Every case below came out of the live export.
 */
class OpenFoodFactsPlacementTest extends TestCase
{
    private function placement(): OpenFoodFactsPlacement
    {
        return new OpenFoodFactsPlacement;
    }

    private function row(array $overrides = []): OpenFoodFactsRow
    {
        return OpenFoodFactsRow::fromCsv(array_merge([
            'source' => 'food', 'barcode' => '6220000000001',
            'name' => '', 'name_ar' => '', 'name_en' => '', 'generic_name' => '',
            'brand' => '', 'brand_slug' => '', 'quantity' => '',
            'quantity_value' => '', 'quantity_unit' => '',
            'categories' => '', 'image_url' => '', 'lang' => 'en', 'stores' => '',
        ], $overrides));
    }

    /*
    |--------------------------------------------------------------------------
    | Which shelf
    |--------------------------------------------------------------------------
    */

    /** Tags run general → specific, so the answer is read from the end. */
    public function test_the_most_specific_tag_decides_the_department(): void
    {
        $row = $this->row([
            'categories' => 'en:beverages-and-beverages-preparations|en:beverages|en:carbonated-drinks|en:sodas',
        ]);

        $this->assertSame('soft_drinks', $this->placement()->department($row));
    }

    public function test_a_row_with_no_category_is_placed_nowhere(): void
    {
        $this->assertNull($this->placement()->department($this->row()));
    }

    /** 269 distinct leaf tags appeared; most are not in the map, and must not guess. */
    public function test_an_unmapped_tag_places_nothing(): void
    {
        $this->assertNull($this->placement()->department($this->row(['categories' => 'ar:شيبسي'])));
        $this->assertNull($this->placement()->department($this->row(['categories' => 'en:incorrect-product-type'])));
    }

    /*
    |--------------------------------------------------------------------------
    | What it is called
    |--------------------------------------------------------------------------
    */

    /**
     * English compounds are head-FINAL. Taking the first noun instead produced
     * «كريمة كامل الدسم لبن» — a cream described as milk.
     *
     * @dataProvider headFinalNames
     */
    public function test_the_last_noun_is_what_the_thing_is(string $english, string $expected): void
    {
        $name = $this->placement()->arabicName($this->row(['name_en' => $english]), '');

        $this->assertSame($expected, $name);
    }

    public static function headFinalNames(): array
    {
        return [
            ['Full Cream Milk', 'لبن كامل الدسم'],
            ['Chocolate Milk', 'لبن شوكولاتة'],
            ['Oat Milk', 'لبن شوفان'],
            ['cream cheese', 'جبنة كريمة'],
            ['Tomato ketchup', 'كاتشب طماطم'],
            ['Natural Yoghurt', 'زبادي طبيعي'],
        ];
    }

    /** «full cream» is one idea; translated word by word it says «cream» twice. */
    public function test_a_two_word_phrase_is_translated_once(): void
    {
        $this->assertSame(
            'لبن كامل الدسم',
            $this->placement()->arabicName($this->row(['name_en' => 'Full cream milk']), '')
        );
    }

    /** A phrase outranks a single noun as the head: this is a lotion, not a butter. */
    public function test_a_phrase_outranks_a_bare_noun_for_the_head(): void
    {
        $name = $this->placement()->arabicName(
            $this->row(['name_en' => 'Body Lotion Cocoa Butter', 'brand' => 'Nivea']),
            'نيفيا'
        );

        $this->assertStringStartsWith('نيفيا لوشن للجسم', (string) $name);
    }

    /** One unknown word and nothing is written. */
    public function test_an_unknown_word_refuses_the_whole_name(): void
    {
        $this->assertNull(
            $this->placement()->arabicName($this->row(['name_en' => 'Speculoos Biscuits']), '')
        );
    }

    public function test_a_name_with_no_noun_at_all_is_refused(): void
    {
        $this->assertNull(
            $this->placement()->arabicName($this->row(['name_en' => 'Extra Premium']), '')
        );
    }

    /*
    |--------------------------------------------------------------------------
    | The Arabic the source already carries
    |--------------------------------------------------------------------------
    */

    /** What a contributor typed in Arabic beats any translation of mine. */
    public function test_a_real_arabic_name_is_kept_and_given_its_brand(): void
    {
        $name = $this->placement()->arabicName(
            $this->row(['name_ar' => 'جبنه فيتا', 'name_en' => 'Feta Cheese']),
            'عبور لاند'
        );

        $this->assertSame('عبور لاند جبنه فيتا', $name);
    }

    /**
     * The source writes «ڤيتراك» where the catalog holds «فيتراك». Compared
     * unfolded they read as two words, and the brand was prefixed to a name
     * that already had it: «فيتراك ڤيتراك مربى مشمش».
     */
    public function test_the_brand_is_not_prefixed_twice_over_a_spelling(): void
    {
        $name = $this->placement()->arabicName(
            $this->row(['name_ar' => 'ڤيتراك مربى مشمش']),
            'فيتراك'
        );

        $this->assertSame('ڤيتراك مربى مشمش', $name);
    }

    /** «تايجر» in the Arabic field of a Tiger product is the brand, not a name. */
    public function test_the_brand_typed_into_the_name_field_is_not_a_name(): void
    {
        $name = $this->placement()->arabicName(
            $this->row(['name_ar' => 'تايجر', 'name_en' => 'Tiger Chips', 'brand' => 'Tiger']),
            'تايجر'
        );

        $this->assertSame('تايجر شيبس', $name, 'the translation should have run instead');
    }

    /** «لمار لتر» is a misspelt brand and a unit. It names nothing. */
    public function test_a_brand_and_a_unit_is_not_a_name(): void
    {
        $name = $this->placement()->arabicName(
            $this->row(['name_ar' => 'لمار لتر', 'name_en' => 'Full cream milk', 'brand' => 'Lamar']),
            'لامار'
        );

        $this->assertSame('لامار لبن كامل الدسم', $name);
    }

    /*
    |--------------------------------------------------------------------------
    | What the whole-database export taught, 2026-08-13
    |--------------------------------------------------------------------------
    */

    /**
     * «whole» on its own is «كامل الدسم» — a statement about FAT — and it went
     * onto a loaf of bread as «توست كامل الدسم قمح».
     */
    public function test_whole_wheat_is_about_grain_not_fat(): void
    {
        $name = $this->placement()->arabicName(
            $this->row(['name_en' => 'Whole wheat sugar-free toast']),
            ''
        );

        $this->assertSame('توست قمح كامل بدون سكر', $name);
        $this->assertStringNotContainsString('الدسم', (string) $name);
    }

    /** …and «whole milk» still is about fat. */
    public function test_whole_milk_is_still_about_fat(): void
    {
        $this->assertSame(
            'لبن كامل الدسم',
            $this->placement()->arabicName($this->row(['name_en' => 'Whole Milk']), '')
        );
    }

    /**
     * «fava» and «beans» are both «فول», and «Premium Fava Beans» came out
     * «فول بريميوم فول». A word that repeats the head says nothing twice.
     */
    public function test_a_word_that_repeats_the_head_is_dropped(): void
    {
        $this->assertSame(
            'فول بريميوم',
            $this->placement()->arabicName($this->row(['name_en' => 'Premium Fava Beans']), '')
        );
    }

    /**
     * A contributor's Arabic name that already states a size must not be given
     * a second one: «حليب بالشيكولاتة 0.9لتر ٨٥٠ مل» disagreed with itself.
     */
    public function test_an_arabic_name_that_states_a_size_gets_no_second_one(): void
    {
        $name = $this->placement()->arabicName(
            $this->row(['name_ar' => 'حليب بالشيكولاتة 0.9لتر']),
            'مزارع دينا',
            '٨٥٠ مل'
        );

        $this->assertSame('مزارع دينا حليب بالشيكولاتة 0.9لتر', $name);
    }

    /*
    |--------------------------------------------------------------------------
    | The package
    |--------------------------------------------------------------------------
    */

    /** The hand-written batch writes «٦٠٠ مل», so this one does too. */
    public function test_the_package_label_reads_like_the_rest_of_the_catalog(): void
    {
        $placement = $this->placement();

        $this->assertSame('٦٠٠ مل', $placement->packageLabel(['value' => 600.0, 'type' => 'volume']));
        $this->assertSame('١ لتر', $placement->packageLabel(['value' => 1000.0, 'type' => 'volume']));
        $this->assertSame('٥٠٠ جم', $placement->packageLabel(['value' => 500.0, 'type' => 'weight']));
        $this->assertSame('١ كجم', $placement->packageLabel(['value' => 1000.0, 'type' => 'weight']));
        $this->assertSame('', $placement->packageLabel(null));
    }

    /** …and the number stored beside it matches the unit chosen for the label. */
    public function test_the_stored_unit_agrees_with_the_label(): void
    {
        $this->assertSame(
            ['code' => 'l', 'value' => 1.5],
            $this->placement()->unitFor(['value' => 1500.0, 'type' => 'volume'])
        );

        $this->assertSame(
            ['code' => 'g', 'value' => 250.0],
            $this->placement()->unitFor(['value' => 250.0, 'type' => 'weight'])
        );
    }

    /*
    |--------------------------------------------------------------------------
    | The files behind all of it
    |--------------------------------------------------------------------------
    */

    /** Every department the map names must exist, or rows vanish silently. */
    public function test_every_mapped_department_is_a_real_catalog_child(): void
    {
        $slugs = \Illuminate\Support\Facades\DB::table('product_category_children as ch')
            ->join('product_categories as c', 'c.id', '=', 'ch.product_category_id')
            ->where('c.slug', 'grocery_retail')
            ->pluck('ch.slug')
            ->all();

        $mapped = array_unique(array_values(require database_path('seeders/data/catalog/off_department_map.php')));

        $this->assertSame(
            [],
            array_values(array_diff($mapped, $slugs)),
            'the map points at a department the grocery branch does not have'
        );
    }
}
