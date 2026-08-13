<?php

namespace App\Services\Catalog;

/**
 * Decides whether an Open Food Facts row can become a catalog product at all —
 * which shelf it belongs on, and what it is called in Arabic.
 *
 * Both answers are refusable, and refusing is the common case. Of the Egypt
 * export, two rows in three carry no category, and a department guessed from a
 * product name is how a shampoo lands on the dairy shelf. A row that cannot be
 * placed or cannot be named is reported, never invented into shape.
 *
 * @see \App\Console\Commands\ImportOpenFoodFacts
 */
class OpenFoodFactsPlacement
{
    /** @var array<string,string> */
    private array $departments;

    /** @var array<string,string> */
    private array $nouns;

    /** @var array<string,string> */
    private array $attributes;

    /** @var array<string,string> */
    private array $nounPhrases;

    /** @var array<string,string> */
    private array $attributePhrases;

    /** @var array<string,string> Arabic head noun → department */
    private array $nounDepartments;

    public function __construct(?array $departments = null, ?array $terms = null, ?array $nounDepartments = null)
    {
        $this->departments = $departments ?? require database_path('seeders/data/catalog/off_department_map.php');
        $this->nounDepartments = $nounDepartments
            ?? require database_path('seeders/data/catalog/off_noun_departments.php');

        $terms ??= require database_path('seeders/data/catalog/off_terms.php');
        $this->nouns = $terms['nouns'] ?? [];
        $this->attributes = $terms['attributes'] ?? [];
        $this->nounPhrases = $terms['noun_phrases'] ?? [];
        $this->attributePhrases = $terms['attribute_phrases'] ?? [];
    }

    /**
     * Arabic folded for COMPARISON only — never for storage.
     *
     * The source spells a brand «ڤيتراك» where the catalog holds «فيتراك», and
     * without folding the two read as different words, so the brand was
     * prefixed to a name that already carried it: «فيتراك ڤيتراك مربى مشمش».
     */
    public static function arabicFold(string $value): string
    {
        $value = strtr($value, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
            'ڤ' => 'ف', 'پ' => 'ب', 'چ' => 'ج', 'گ' => 'ك',
        ]);

        // Diacritics and tatweel say nothing about which word this is.
        $value = (string) preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value);

        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * The department slug, or null when the row says nothing that places it.
     *
     * Tags are scanned from the LAST — Open Food Facts writes them general to
     * specific, so «en:beverages … en:sodas» must answer «soft_drinks», not
     * whatever the broadest tag happens to map to.
     */
    public function department(OpenFoodFactsRow $row): ?string
    {
        $tags = array_values(array_filter(array_map('trim', explode('|', $row->categories))));

        foreach (array_reverse($tags) as $tag) {
            $key = strtolower($tag);

            if (isset($this->departments[$key])) {
                return $this->departments[$key];
            }
        }

        // What the source DECLARES outranks what the name implies — but 721
        // Egyptian rows carry a brand and a plain type and no category at all,
        // and «لبن» belongs on the milk shelf whatever the tags omit.
        $head = $this->headNoun($row);

        return $head === null ? null : ($this->nounDepartments[$head] ?? null);
    }

    /**
     * The TYPE: what the thing is and what kind of it — «لبن كامل الدسم»,
     * «جبنة كريمي» — with the brand and the size left out.
     *
     * «يبقى المطلوب الاسم التجارى والنوع والحجم فقط». The three are separate
     * facts and the catalog has a column for each: the brand is `brand_id`, the
     * size is `package_label_ar`, and this is what goes in `short_name_ar`. A
     * name is the three run together; keeping the type on its own is what lets
     * one type be listed once with its sizes underneath.
     */
    public function type(OpenFoodFactsRow $row): ?string
    {
        return $this->translate($row);
    }

    /** The Arabic head noun alone, or null when no word in the name is one. */
    private function headNoun(OpenFoodFactsRow $row): ?string
    {
        $tokens = $this->foldPhrases($row->tokenSet());
        $head = null;

        foreach ($tokens as $token) {
            if (str_starts_with($token, '=')) {
                $head = substr($token, 1);
            }
        }

        if ($head !== null) {
            return $head;
        }

        foreach ($tokens as $token) {
            if (isset($this->nouns[$token])) {
                $head = $this->nouns[$token];
            }
        }

        return $head;
    }

    /**
     * The Arabic name: «<brand> <noun> <attributes> <package>».
     *
     * Null unless EVERY word is known. A half-translated name — «جبنة feta» —
     * is worse than none: it looks finished, so nobody comes back to fix it.
     *
     * @param  string  $brandAr  the brand's Arabic name as the catalog holds it
     */
    public function arabicName(OpenFoodFactsRow $row, string $brandAr, string $packageLabel = ''): ?string
    {
        $brandAr = trim($brandAr);

        // What the contributor actually wrote in Arabic beats any translation
        // of mine — but it is a product name, not a shelf label, so the brand
        // still goes in front unless it is already there.
        if ($row->hasArabicName() && ! $this->isJustTheBrand($row->nameAr, $brandAr)) {
            $folded = self::arabicFold($row->nameAr);
            $name = $brandAr !== '' && ! str_contains($folded, self::arabicFold($brandAr))
                ? $brandAr . ' ' . $row->nameAr
                : $row->nameAr;

            // «حليب بالشيكولاتة 0.9لتر» already states its size. Appending the
            // label on top gave a name carrying two of them, disagreeing.
            if (preg_match('/[\d٠-٩]/u', $row->nameAr)) {
                return trim($name);
            }

            return trim($name . ' ' . $packageLabel);
        }

        $type = $this->translate($row);

        if ($type === null) {
            return null;
        }

        return trim(implode(' ', array_filter([$brandAr, $type, trim($packageLabel)])));
    }

    /**
     * The type alone, translated from the English name — no brand, no size.
     *
     * Split out of `arabicName()` so the two can never disagree: the name is
     * this with a brand in front and a size behind, and `short_name_ar` is this
     * on its own.
     */
    private function translate(OpenFoodFactsRow $row): ?string
    {
        $tokens = $this->foldPhrases($row->tokenSet());

        if ($tokens === []) {
            return null;
        }

        // English compounds are head-FINAL: «full cream MILK» is a milk,
        // «cream CHEESE» is a cheese, «oat MILK» is a milk. Taking the first
        // noun instead produced «كريمة كامل الدسم لبن» — a cream described as
        // milk. The last known noun is the thing; everything else describes it.
        // A phrase outranks a single word for the head: «Body Lotion Cocoa
        // Butter» is a lotion, and taking the last NOUN there makes it a
        // butter. Fall back to the last single noun when no phrase is present.
        $headIndex = null;

        foreach ($tokens as $i => $token) {
            if (str_starts_with($token, '=')) {
                $headIndex = $i;
            }
        }

        if ($headIndex === null) {
            foreach ($tokens as $i => $token) {
                if (isset($this->nouns[$token])) {
                    $headIndex = $i;
                }
            }
        }

        if ($headIndex === null) {
            return null;
        }

        $head = $tokens[$headIndex];
        $noun = str_starts_with($head, '=') ? substr($head, 1) : $this->nouns[$head];
        $rest = [];

        foreach ($tokens as $i => $token) {
            if ($i === $headIndex) {
                continue;
            }

            // An attribute reading is preferred for a word that has both —
            // «chocolate» in «Chocolate Milk» describes the milk.
            $word = str_starts_with($token, '=') || str_starts_with($token, '~')
                ? substr($token, 1)
                : ($this->attributes[$token] ?? $this->nouns[$token] ?? null);

            if ($word === null) {
                return null;
            }

            $rest[] = $word;
        }

        // «Premium Fava Beans» — «fava» and «beans» are both «فول», and the
        // name came out «فول بريميوم فول». A word that repeats the head says
        // nothing a second time.
        $rest = array_values(array_filter(array_unique($rest), fn ($word) => $word !== $noun));

        return trim($noun . ' ' . implode(' ', $rest));
    }

    /** Words that measure the package rather than name the product. */
    private const UNIT_WORDS = ['لتر', 'مل', 'جم', 'كجم', 'جرام', 'كيلو', 'عبوة', 'قطعة', 'علبة', 'كيس'];

    /**
     * Is the Arabic the contributor typed actually a product name?
     *
     * Two real rows say it is worth asking: a Tiger product whose Arabic field
     * holds «تايجر» — the brand, typed where the name goes — and a Lamar milk
     * whose Arabic field holds «لمار لتر», a misspelt brand and a unit. Neither
     * names anything, and both would have become the product's name on the
     * shelf.
     *
     * The test: take out the units and the digits, and see whether one word is
     * left. A one-word Arabic product name is nearly always the brand.
     */
    private function isJustTheBrand(string $nameAr, string $brandAr): bool
    {
        $folded = self::arabicFold($nameAr);

        if ($brandAr !== '' && $folded === self::arabicFold($brandAr)) {
            return true;
        }

        $words = array_values(array_filter(
            preg_split('/\s+/u', $folded) ?: [],
            fn ($word) => $word !== ''
                && ! in_array($word, self::UNIT_WORDS, true)
                && ! preg_match('/^[\d٠-٩.,]+$/u', $word)
        ));

        return count($words) < 2;
    }

    /**
     * Replace adjacent pairs that mean one thing, before any word is looked at
     * on its own. Marked so the caller still knows which are nouns.
     *
     * @param  array<int,string>  $tokens
     * @return array<int,string>  tokens, with phrases as «=ar» (noun) or «~ar»
     */
    private function foldPhrases(array $tokens): array
    {
        $out = [];

        for ($i = 0; $i < count($tokens); $i++) {
            $pair = $tokens[$i] . ' ' . ($tokens[$i + 1] ?? '');

            if (isset($this->nounPhrases[$pair])) {
                $out[] = '=' . $this->nounPhrases[$pair];
                $i++;

                continue;
            }

            if (isset($this->attributePhrases[$pair])) {
                $out[] = '~' . $this->attributePhrases[$pair];
                $i++;

                continue;
            }

            $out[] = $tokens[$i];
        }

        return $out;
    }

    /**
     * The package label the catalog writes — «٦٠٠ مل», «١ كجم» — in the same
     * shape the hand-written rows use, so the two batches read alike.
     */
    public function packageLabel(?array $size): string
    {
        if (! $size) {
            return '';
        }

        [$value, $unit] = $size['type'] === 'weight'
            ? ($size['value'] >= 1000 ? [$size['value'] / 1000, 'كجم'] : [$size['value'], 'جم'])
            : ($size['value'] >= 1000 ? [$size['value'] / 1000, 'لتر'] : [$size['value'], 'مل']);

        $number = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return self::easternDigits($number) . ' ' . $unit;
    }

    /** «600» → «٦٠٠». The hand-written batch writes sizes this way. */
    public static function easternDigits(string $value): string
    {
        return strtr($value, [
            '0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤',
            '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩',
            '.' => '.',
        ]);
    }

    /** The unit code and value the catalog stores alongside the label. */
    public function unitFor(?array $size): ?array
    {
        if (! $size) {
            return null;
        }

        return $size['type'] === 'weight'
            ? ($size['value'] >= 1000
                ? ['code' => 'kg', 'value' => $size['value'] / 1000]
                : ['code' => 'g', 'value' => $size['value']])
            : ($size['value'] >= 1000
                ? ['code' => 'l', 'value' => $size['value'] / 1000]
                : ['code' => 'ml', 'value' => $size['value']]);
    }
}
