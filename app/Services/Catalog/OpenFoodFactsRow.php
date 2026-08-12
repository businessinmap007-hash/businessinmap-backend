<?php

namespace App\Services\Catalog;

/**
 * One row of the Open Food Facts export, read the same way everywhere.
 *
 * The fetcher writes the source verbatim — a fetcher that also decides what a
 * quantity means produces output nobody can check. Every judgement about what
 * a row SAYS is made here instead, once, so the matcher and the importer can
 * never disagree about the same row.
 *
 * @see \App\Console\Commands\FetchOpenFoodFactsEgypt
 */
class OpenFoodFactsRow
{
    /** Base units: weight in grams, volume in millilitres. */
    private const WEIGHT = ['g' => 1, 'gr' => 1, 'gram' => 1, 'grams' => 1, 'gm' => 1, 'kg' => 1000, 'kgs' => 1000, 'ton' => 1000000];

    private const VOLUME = ['ml' => 1, 'cl' => 10, 'dl' => 100, 'l' => 1000, 'lt' => 1000, 'ltr' => 1000, 'liter' => 1000, 'litre' => 1000];

    /**
     * Words that say nothing about WHICH product this is. Left in, «Juhayna
     * Orange Juice 1L» and «Juhayna Mango Juice 1L» share a token and start to
     * look alike; the margin rule would still separate them, but only just.
     */
    private const NOISE = [
        'the', 'and', 'with', 'for', 'from', 'new', 'original', 'classic',
        'pack', 'packet', 'bottle', 'can', 'box', 'bag', 'jar', 'tin',
        'egypt', 'egyptian', 'product', 'products',
    ];

    public function __construct(
        public readonly string $source,
        public readonly string $barcode,
        public readonly string $name,
        public readonly string $nameAr,
        public readonly string $brand,
        public readonly string $quantity,
        public readonly ?float $quantityValue,
        public readonly string $quantityUnit,
        public readonly string $categories,
        public readonly string $imageUrl,
        public readonly string $lang,
    ) {}

    /** @param  array<string,string>  $row  a line of the fetcher's CSV, keyed by header */
    public static function fromCsv(array $row): self
    {
        // `product_name` is whatever language the contributor typed in;
        // `product_name_en` is the English field when someone filled it. Prefer
        // the explicit English one, fall back to whatever there is.
        $name = trim((string) ($row['name_en'] ?? ''));

        if ($name === '') {
            $name = trim((string) ($row['name'] ?? ''));
        }

        $value = trim((string) ($row['quantity_value'] ?? ''));

        return new self(
            source: trim((string) ($row['source'] ?? '')),
            barcode: trim((string) ($row['barcode'] ?? '')),
            name: $name,
            nameAr: self::cleanArabic((string) ($row['name_ar'] ?? '')),
            brand: trim((string) ($row['brand'] ?? '')),
            quantity: trim((string) ($row['quantity'] ?? '')),
            quantityValue: ($value === '' || (float) $value <= 0) ? null : (float) $value,
            quantityUnit: strtolower(trim((string) ($row['quantity_unit'] ?? ''))),
            categories: trim((string) ($row['categories'] ?? '')),
            imageUrl: trim((string) ($row['image_url'] ?? '')),
            lang: strtolower(trim((string) ($row['lang'] ?? ''))),
        );
    }

    /**
     * An Arabic name only counts if it actually contains Arabic.
     *
     * The field is free text and people put anything in it: a full stop, a
     * latin transliteration («Mix juhayna»), the barcode. What is not Arabic
     * script is not an Arabic name, whatever column it arrived in.
     */
    public static function cleanArabic(string $value): string
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/\p{Arabic}/u', $value)) {
            return '';
        }

        // At least two Arabic letters — «.» and «أ» are not names either.
        return preg_match_all('/\p{Arabic}/u', $value) >= 2 ? $value : '';
    }

    public function hasArabicName(): bool
    {
        return $this->nameAr !== '';
    }

    /**
     * The package size in a base unit, or null when the row does not state one.
     *
     * @return array{value:float,type:string}|null
     */
    public function size(): ?array
    {
        // A number with no unit says nothing. The whole-database export omits
        // `product_quantity_unit`, and reading 1500 there as 1500 GRAMS turns
        // a litre and a half of juice into a weight — which the size gate then
        // refuses against every correct candidate. The raw «1.5 L» string is
        // the better witness whenever the unit column is silent.
        if ($this->quantityValue !== null && $this->quantityUnit !== '') {
            $size = self::normalise($this->quantityValue, $this->quantityUnit);

            if ($size) {
                return $size;
            }
        }

        // No unit anywhere is no size. Unsized rows still match on name alone
        // and are labelled «matched-without-size», so nothing is lost by
        // declining to guess which kind of unit a bare number was.
        return self::parseSize($this->quantity);
    }

    /**
     * «600ml», «1.5 L» — the first number and unit that parse.
     *
     * A RANGE («58-68 g», real source data) states no size at all, and picking
     * an end of it would be inventing one. Since the size is a hard gate, a
     * wrong number there is worse than none: it would refuse the right barcode
     * or wave through the wrong one. Null means «the source did not say», and
     * the match is labelled as made without a size.
     */
    public static function parseSize(string $text): ?array
    {
        if (preg_match('/\d\s*[-–]\s*\d/u', $text)) {
            return null;
        }

        if (! preg_match('/(\d+(?:[.,]\d+)?)\s*([a-zA-Z]+)/u', $text, $m)) {
            return null;
        }

        return self::normalise((float) str_replace(',', '.', $m[1]), strtolower($m[2]));
    }

    /** @return array{value:float,type:string}|null */
    public static function normalise(float $value, string $unit): ?array
    {
        $unit = strtolower(trim($unit));

        if (isset(self::WEIGHT[$unit])) {
            return ['value' => $value * self::WEIGHT[$unit], 'type' => 'weight'];
        }

        if (isset(self::VOLUME[$unit])) {
            return ['value' => $value * self::VOLUME[$unit], 'type' => 'volume'];
        }

        // Counts (pieces, packs) are not a size anything can be compared on.
        return null;
    }

    /**
     * A brand reduced to what is left when spelling stops mattering: «Nestlé»,
     * «nestle», «NESTLE S.A.» and «nestle_pure_life» all start here.
     */
    public static function brandKey(string $brand): string
    {
        // Only the first brand when several are listed — «chipsy شيبسي» and
        // «Dan GoPro Hipro» are one shelf brand each, however they were typed.
        $brand = (string) preg_split('/[,|]/u', $brand)[0];

        return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', self::latinise($brand)));
    }

    /**
     * «Nescafé» → «Nescafe».
     *
     * Applied to NAMES as well as brands, and that is not a nicety: stripping
     * non-ASCII without folding first turns «Nescafé» into «Nescaf», which
     * then no longer matches its own brand token and survives into the name as
     * a word of its own — halving the score of a correct match.
     */
    public static function latinise(string $value): string
    {
        return strtr($value, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'å' => 'a',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ç' => 'c', 'ñ' => 'n', 'ß' => 'ss',
        ]);
    }

    public function brandKeyValue(): string
    {
        return self::brandKey($this->brand);
    }

    /**
     * The words that say which product this is: the name with its brand, its
     * size and every non-latin character taken out.
     *
     * Non-latin goes because the catalog's own `name_en` carries the Arabic
     * package label glued to the end («Kellogg's Corn Flakes ٥٠٠ جم») and
     * those characters are not part of the English name.
     *
     * @return array<int,string>
     */
    public static function tokens(string $name, string $brand = ''): array
    {
        $name = (string) preg_replace('/[^\x20-\x7E]/u', ' ', self::latinise($name));
        $name = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', ' ', $name));

        $brandTokens = $brand === ''
            ? []
            : (preg_split('/\s+/', trim((string) preg_replace(
                '/[^a-zA-Z0-9]+/', ' ', strtolower(self::latinise($brand))
            ))) ?: []);

        $tokens = [];

        foreach (preg_split('/\s+/', trim($name)) ?: [] as $token) {
            if ($token === '' || strlen($token) < 3) {
                continue;
            }

            // A bare number, or a number welded to a unit, is the size — and
            // the size is compared as a number, never as a word.
            if (preg_match('/^\d/', $token)) {
                continue;
            }

            if (in_array($token, self::NOISE, true) || in_array($token, $brandTokens, true)) {
                continue;
            }

            $tokens[$token] = true;
        }

        return array_keys($tokens);
    }

    /** @return array<int,string> */
    public function tokenSet(): array
    {
        return self::tokens($this->name, $this->brand);
    }
}
