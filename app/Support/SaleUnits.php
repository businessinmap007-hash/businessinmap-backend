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
            // Units of a SALE only: حصان، بوصة، شعلة، جيجا، وات describe a specification and were
            // being offered to a greengrocer beside الكيلو (2026-10-04).
            ->where('is_sale_unit', 1)
            ->orderByRaw("FIELD(unit_type, 'count', 'weight', 'volume', 'length')")
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

    /**
     * The sale units a line GROUP is sold in — decided by its detail type («أنواع التفاصيل»), never by a
     * list of names kept in code. null = every sale unit (a group with no type, or the basic one).
     *
     * @param  int|string  $group  the option group's id, or its Arabic name
     * @return list<string>|null
     */
    public static function codesForGroup(int|string $group): ?array
    {
        $type = DB::table('option_groups')
            ->when(is_int($group), fn ($q) => $q->where('id', $group), fn ($q) => $q->where('name_ar', $group))
            ->value('detail_type');

        return $type ? self::codesForType((string) $type) : null;
    }

    /** @return list<string>|null null = every sale unit */
    public static function codesForType(string $type): ?array
    {
        $json = DB::table('menu_detail_types')->where('code', $type)->value('sale_unit_codes');
        $codes = $json ? json_decode((string) $json, true) : null;

        // Only codes that exist as sale units today — a fresh install without a newer unit still gets the rest.
        return is_array($codes) ? array_values(array_intersect($codes, self::codes())) : null;
    }

    /** The units a customer may order a PART of: the kilo and the litre («كيلو وربع ونص»). */
    public const FRACTIONAL = ['kg', 'l', 'liter'];

    /** Is an item sold in [$code] ordered by weight or volume, in fractions? */
    public static function isFractional(?string $code): bool
    {
        return in_array(trim((string) $code), self::FRACTIONAL, true);
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
        $codes = self::codesForType('pharmacy') ?? ['pack', 'strip', 'pcs'];

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

    /**
     * «الورقيات بتكون اما بالرابطة او بالكيلو او جرام مثلها مثل الفواكة
     * والخضروات فلذلك اجعل الوحدات فيها بالثلاثة دول فقط» — المالك،
     * 2026-09-29. بقدونس، كزبرة، شبت… never sell by the litre or the box —
     * a bunch, a kilo or a gram covers every real case. `bunch` is a
     * 2026-09-29 addition ({@see \Database\Seeders\HerbsBunchUnitSeeder});
     * a fresh install that has not yet run it still gets كجم/جم rather
     * than an empty dropdown, same guard as {@see pharmacyOptions()}.
     *
     * @return array<string,string> code => Arabic label
     */
    public static function herbsOptions(): array
    {
        $codes = self::codesForType('fresh_produce') ?? ['bunch', 'kg', 'g'];

        return array_filter(
            self::options(),
            fn ($label, $code) => in_array($code, $codes, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /** @return array<int,string> */
    public static function herbsCodes(): array
    {
        return array_keys(self::herbsOptions());
    }

    /**
     * The option groups narrowed to {@see herbsCodes()} (bunch/kg/g) —
     * widened the same day from herbs alone to include فواكه/خضروات too,
     * per the owner's own comparison in the quote above: fruit and
     * vegetables never sell by the litre or the box either.
     *
     * @return array<int,string>
     */
    public static function producePackagingGroupNames(): array
    {
        return ['أعشاب وورقيات', 'الفواكه', 'الخضروات'];
    }
}
