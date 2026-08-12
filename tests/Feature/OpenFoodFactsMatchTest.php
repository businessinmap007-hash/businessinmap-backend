<?php

namespace Tests\Feature;

use App\Services\Catalog\OpenFoodFactsMatcher;
use App\Services\Catalog\OpenFoodFactsRow;
use Tests\TestCase;

/**
 * «هل يمكنك جلب اصناف السوبر ماركت كاملة كما فعلت فى الادوية».
 *
 * There is no register of Egyptian supermarket SKUs the way there is one of
 * medicines. Open Food Facts is the nearest real source — 4,590 Egypt-tagged
 * products carrying genuine EAN-13 barcodes — but it is crowdsourced, so a
 * brand arrives spelled three ways, a quantity as «58-68 g», and an Arabic
 * name on barely one row in seven.
 *
 * That makes the matcher the whole safety of this import: the 986 catalog rows
 * were written from knowledge, and attaching the WRONG barcode to one of them
 * is worse than leaving it bare — a scanner would then confidently return the
 * wrong product. What is asserted here is that the matcher refuses more
 * readily than it accepts.
 */
class OpenFoodFactsMatchTest extends TestCase
{
    private function row(array $overrides = []): OpenFoodFactsRow
    {
        return OpenFoodFactsRow::fromCsv(array_merge([
            'source' => 'food',
            'barcode' => '6221234567890',
            'name' => 'Mango Juice',
            'name_ar' => '',
            'name_en' => 'Mango Juice',
            'generic_name' => '',
            'brand' => 'Juhayna',
            'brand_slug' => 'juhayna',
            'quantity' => '1L',
            'quantity_value' => '1000',
            'quantity_unit' => 'ml',
            'categories' => 'en:beverages',
            'image_url' => 'https://images.openfoodfacts.org/x.jpg',
            'lang' => 'en',
            'stores' => '',
        ], $overrides));
    }

    /*
    |--------------------------------------------------------------------------
    | Reading the source
    |--------------------------------------------------------------------------
    */

    /** «Mix juhayna» arrived in a field called `product_name_ar`. It is not Arabic. */
    public function test_a_latin_string_in_the_arabic_column_is_not_an_arabic_name(): void
    {
        $this->assertSame('', OpenFoodFactsRow::cleanArabic('Mix juhayna'));
        $this->assertSame('', OpenFoodFactsRow::cleanArabic('.'));
        $this->assertSame('', OpenFoodFactsRow::cleanArabic(''));
        $this->assertSame('تايجر', OpenFoodFactsRow::cleanArabic('تايجر'));
    }

    /** A single Arabic letter is a typo, not a name. */
    public function test_one_stray_arabic_letter_is_not_a_name(): void
    {
        $this->assertSame('', OpenFoodFactsRow::cleanArabic('أ'));
    }

    public function test_sizes_normalise_to_one_base_per_kind(): void
    {
        $this->assertSame(['value' => 1000.0, 'type' => 'volume'], OpenFoodFactsRow::normalise(1, 'L'));
        $this->assertSame(['value' => 1000.0, 'type' => 'weight'], OpenFoodFactsRow::normalise(1, 'kg'));
        $this->assertSame(['value' => 500.0, 'type' => 'weight'], OpenFoodFactsRow::normalise(500, 'gram'));

        // A count is not a size. «عبوة» cannot be compared with anything.
        $this->assertNull(OpenFoodFactsRow::normalise(1, 'pack'));
        $this->assertNull(OpenFoodFactsRow::normalise(4, 'pcs'));
    }

    public function test_a_dirty_quantity_still_yields_a_size(): void
    {
        $this->assertSame(['value' => 16.0, 'type' => 'weight'], OpenFoodFactsRow::parseSize('16g'));
        $this->assertSame(['value' => 1500.0, 'type' => 'volume'], OpenFoodFactsRow::parseSize('1.5L'));
        $this->assertSame(['value' => 700.0, 'type' => 'weight'], OpenFoodFactsRow::parseSize('700 g'));
    }

    /**
     * «58-68 g» is real source data, and it states no size. Picking an end of
     * the range would invent one — and since the size is a hard gate, an
     * invented number refuses the right barcode or admits the wrong one.
     */
    public function test_a_range_is_not_a_size(): void
    {
        $this->assertNull(OpenFoodFactsRow::parseSize('58-68 g'));
    }

    /** The catalog's own `name_en` carries the Arabic label glued on the end. */
    public function test_tokens_drop_the_brand_the_size_and_the_arabic_tail(): void
    {
        $tokens = OpenFoodFactsRow::tokens("Kellogg's Corn Flakes ٥٠٠ جم", 'Kelloggs');

        $this->assertContains('corn', $tokens);
        $this->assertContains('flakes', $tokens);
        $this->assertNotContains('500', $tokens);
        $this->assertSame([], array_filter($tokens, fn ($t) => preg_match('/[^a-z0-9]/', $t)));
    }

    public function test_brand_spelling_stops_mattering(): void
    {
        $this->assertSame('nestle', OpenFoodFactsRow::brandKey('Nestlé'));
        $this->assertSame('nestle', OpenFoodFactsRow::brandKey('NESTLE'));
        $this->assertSame('cocacola', OpenFoodFactsRow::brandKey('Coca-Cola'));
        // Only the first of several — «chipsy شيبسي» is one shelf brand.
        $this->assertSame('chipsy', OpenFoodFactsRow::brandKey('chipsy شيبسي'));
    }

    /*
    |--------------------------------------------------------------------------
    | Matching
    |--------------------------------------------------------------------------
    */

    /** Near-identity, spelled differently, is what may pass automatically. */
    public function test_it_matches_the_same_name_spelled_differently(): void
    {
        $matcher = new OpenFoodFactsMatcher([
            $this->row(['barcode' => '1', 'name_en' => 'Nescafé gold', 'brand' => 'Nescafe']),
        ]);

        $result = $matcher->match('Nescafe', 'Nescafe Nescafe Gold ١٠٠ جم', ['value' => 1000.0, 'type' => 'volume']);

        $this->assertNotNull($result['row']);
        $this->assertSame('1', $result['row']->barcode);
    }

    /**
     * Every one of these was produced as a confident automatic match by the
     * first live run, at score 1.00, by containment scoring. They are the
     * reason the measure is symmetric now: the extra word on the source side
     * is not detail, it is a DIFFERENT PRODUCT.
     *
     * @dataProvider variantsThatAreNotTheSameProduct
     */
    public function test_a_more_specific_source_name_is_a_different_product(string $catalog, string $source, string $brand): void
    {
        $matcher = new OpenFoodFactsMatcher([
            $this->row(['barcode' => '1', 'name_en' => $source, 'brand' => $brand, 'quantity_value' => '']),
        ]);

        $result = $matcher->match($brand, $catalog, null);

        $this->assertNull(
            $result['row'],
            "«{$catalog}» was given «{$source}»'s barcode at score " . $result['score']
        );
    }

    public static function variantsThatAreNotTheSameProduct(): array
    {
        return [
            'berries is not plain' => ['Juhayna Greek Yoghurt', 'Greek Mixed Berries Yoghurt', 'Juhayna'],
            'light is another SKU' => ['Heinz Mayonnaise', 'Light mayonnaise', 'Heinz'],
            'salt is not salt & vinegar' => ['Chipsy Salt Chips', 'salt and vinegar chips', 'Chipsy'],
            'for men is another line' => ['Nivea Roll-on Deodorant', 'Nivea for Men Cool Kick Deodorant Roll-on', 'Nivea'],
            'vegetable fat is not feta' => ['Domty Feta Cheese', 'Feta Cheese Vegetable Fat', 'Domty'],
            'generic beats nothing' => ['Regina Fusilli Pasta', 'Pasta', 'Regina'],
        ];
    }

    /**
     * Two catalog rows reaching for one barcode means one of them is wrong, or
     * they are a duplicate pair. Neither gets it. This is real: «Regina Penne»
     * and «Regina Shells Pasta» both reached for a source row named «Pasta».
     */
    public function test_one_barcode_cannot_be_claimed_twice(): void
    {
        $this->assertStringContainsString(
            'barcode-already-claimed-by-',
            file_get_contents(base_path('app/Console/Commands/MatchOpenFoodFacts.php')),
            'the collision guard is gone'
        );
    }

    /**
     * The point of the whole catalog design: «جهينة ١ لتر» and «جهينة ١.٥ لتر»
     * are two products. A barcode may never cross that line.
     */
    public function test_a_different_size_is_a_different_product(): void
    {
        $matcher = new OpenFoodFactsMatcher([
            $this->row(['barcode' => '1', 'quantity_value' => '1500']),
        ]);

        $result = $matcher->match('Juhayna', 'Juhayna Mango Juice', ['value' => 1000.0, 'type' => 'volume']);

        $this->assertNull($result['row'], 'a 1L product was given a 1.5L barcode');
    }

    /** Grams are not millilitres even when the numbers agree. */
    public function test_weight_never_matches_volume(): void
    {
        $matcher = new OpenFoodFactsMatcher([
            $this->row(['barcode' => '1', 'quantity_value' => '1000', 'quantity_unit' => 'g']),
        ]);

        $this->assertNull(
            $matcher->match('Juhayna', 'Juhayna Mango Juice', ['value' => 1000.0, 'type' => 'volume'])['row']
        );
    }

    /**
     * Two source rows the catalog name fits equally well. The source is not
     * saying which — so neither does the matcher, even at a perfect score.
     */
    public function test_a_tie_at_the_top_is_refused_not_broken(): void
    {
        $matcher = new OpenFoodFactsMatcher([
            $this->row(['barcode' => '1', 'name_en' => 'Mango Juice']),
            $this->row(['barcode' => '2', 'name_en' => 'Juice Mango']),
        ]);

        $result = $matcher->match('Juhayna', 'Juhayna Mango Juice', ['value' => 1000.0, 'type' => 'volume']);

        $this->assertNull($result['row']);
        $this->assertSame('ambiguous', $result['reason']);
        $this->assertSame(1.0, round($result['score'], 2), 'it was refused despite scoring perfectly');
    }

    /** Two juices, one brand, one size — neither name is near enough anyway. */
    public function test_two_close_candidates_are_refused_not_guessed(): void
    {
        $matcher = new OpenFoodFactsMatcher([
            $this->row(['barcode' => '1', 'name_en' => 'Mango Nectar Juice']),
            $this->row(['barcode' => '2', 'name_en' => 'Mango Nectar Drink']),
        ]);

        $this->assertNull(
            $matcher->match('Juhayna', 'Juhayna Mango Nectar', ['value' => 1000.0, 'type' => 'volume'])['row']
        );
    }

    /** …and the clear winner among several still wins. */
    public function test_a_clear_winner_beats_its_neighbours(): void
    {
        $matcher = new OpenFoodFactsMatcher([
            $this->row(['barcode' => '1', 'name_en' => 'Mango Juice']),
            $this->row(['barcode' => '2', 'name_en' => 'Orange Juice']),
            $this->row(['barcode' => '3', 'name_en' => 'Guava Juice']),
        ]);

        $result = $matcher->match('Juhayna', 'Juhayna Mango Juice', ['value' => 1000.0, 'type' => 'volume']);

        $this->assertSame('1', $result['row']?->barcode);
    }

    /** One shared generic word out of many is not a match. */
    public function test_a_single_shared_word_is_not_enough(): void
    {
        $matcher = new OpenFoodFactsMatcher([
            $this->row(['barcode' => '1', 'name_en' => 'Strawberry Yoghurt Drink Light']),
        ]);

        $result = $matcher->match('Juhayna', 'Juhayna Mango Juice Nectar', ['value' => 1000.0, 'type' => 'volume']);

        $this->assertNull($result['row']);
    }

    public function test_an_unknown_brand_matches_nothing(): void
    {
        $matcher = new OpenFoodFactsMatcher([$this->row()]);

        $result = $matcher->match('Halwani', 'Halwani Mango Juice', ['value' => 1000.0, 'type' => 'volume']);

        $this->assertNull($result['row']);
        $this->assertSame('brand-not-in-source', $result['reason']);
    }

    /** Never compare across brands, however alike the names read. */
    public function test_the_same_name_under_another_brand_is_another_product(): void
    {
        $matcher = new OpenFoodFactsMatcher([
            $this->row(['barcode' => '1', 'brand' => 'Beyti', 'name_en' => 'Mango Juice']),
        ]);

        $this->assertNull(
            $matcher->match('Juhayna', 'Juhayna Mango Juice', ['value' => 1000.0, 'type' => 'volume'])['row']
        );
    }

    /**
     * 419 catalog rows say «عبوة» and no more. Refusing every unsized row would
     * throw away nearly half the catalog, so a silent side is not a refusal —
     * but the match it produces is labelled as one made without a size.
     */
    public function test_an_unsized_product_can_still_match_and_says_so(): void
    {
        $matcher = new OpenFoodFactsMatcher([
            $this->row(['barcode' => '1', 'name_en' => 'Triangle Cheese', 'brand' => 'Domty']),
        ]);

        $result = $matcher->match('Domty', 'Domty Triangle Cheese عبوة', null);

        $this->assertSame('1', $result['row']?->barcode);
        $this->assertSame('matched-without-size', $result['reason']);
    }

    /** A row with no barcode is not a source of barcodes. */
    public function test_a_row_without_a_barcode_is_never_a_candidate(): void
    {
        $matcher = new OpenFoodFactsMatcher([$this->row(['barcode' => ''])]);

        $this->assertNull($matcher->match('Juhayna', 'Juhayna Mango Juice', null)['row']);
    }
}
