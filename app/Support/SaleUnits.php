<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The units a shop prices in — كجم، لتر، قطعة.
 *
 * «مثلا منتج يباع فى محل يكون بالوزن، كيلو أو لتر» — المالك، 2026-08-23.
 *
 * Read from `catalog_units` rather than declared here, because that table
 * already holds this vocabulary for the shared product catalog and two lists of
 * the same words drift apart in a month.
 *
 * ⚠ It holds twelve rows and only nine words: `g`/`gram`, `l`/`liter` and
 * `pcs`/`piece` are the same unit twice, left behind by two import batches.
 * Deduplicated on the way out by (name, kind) — the shorter code wins, since
 * that is the one the importer writes — and NOT cleaned up in the table, which
 * is a curation decision and not this file's to make. What matters here is that
 * a merchant is never offered «قطعة» twice in one dropdown.
 */
final class SaleUnits
{
    /**
     * @return array<string,string> code => label in the current app locale
     *         (falling back to whichever of name_ar/name_en is set), ordered
     *         by kind
     */
    public static function options(): array
    {
        static $cache = [];

        $locale = app()->getLocale();

        if (isset($cache[$locale])) {
            return $cache[$locale];
        }

        $rows = DB::table('catalog_units')
            ->where('is_active', 1)
            ->orderByRaw("FIELD(unit_type, 'count', 'weight', 'volume')")
            ->orderBy('sort_order')
            ->orderByRaw('CHAR_LENGTH(code)')
            ->get(['code', 'name_ar', 'name_en', 'unit_type']);

        $seen = [];
        $out = [];

        foreach ($rows as $row) {
            // Deduping stays keyed on the Arabic name regardless of locale —
            // it is what every row actually has, and the two words that
            // collide («جم»/«جرام») always agree on it either way.
            $key = $row->unit_type . '|' . $row->name_ar;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $label = $locale === 'en'
                ? ((string) $row->name_en ?: (string) $row->name_ar)
                : ((string) $row->name_ar ?: (string) $row->name_en);
            $out[(string) $row->code] = $label;
        }

        return $cache[$locale] = $out;
    }

    /** The label to print beside a price, or null when the row sells by the item. */
    public static function label(?string $code): ?string
    {
        $code = trim((string) $code);

        return $code === '' ? null : (self::options()[$code] ?? null);
    }

    /** @return array<int,string> */
    public static function codes(): array
    {
        return array_keys(self::options());
    }

    /**
     * «وحدة البيع عبوة او شريط او قطعة لا يوجد لتر وجرام وكيلو» — المالك،
     * 2026-08-26. A pharmacy never weighs a drug out; it sells the box, the
     * strip, or the loose tablet. Whichever of the three codes exist in
     * `catalog_units` — `strip` is a 2026-08-26 addition and a fresh install
     * that has not yet run {@see \Database\Seeders\PharmacyUnitSeeder} should
     * still get the other two rather than an empty dropdown.
     *
     * @return array<string,string> code => Arabic label
     */
    public static function pharmacyOptions(): array
    {
        $codes = ['pack', 'strip', 'pcs'];

        return array_filter(
            self::options(),
            fn ($label, $code) => in_array($code, $codes, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** @return array<int,string> */
    public static function pharmacyCodes(): array
    {
        return array_keys(self::pharmacyOptions());
    }
}
