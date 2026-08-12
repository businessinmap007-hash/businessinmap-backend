<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One entry in the shared medicine dictionary (see the create migration): a drug
 * name and its strength, contributed by doctors as they write prescriptions.
 */
class Medicine extends Model
{
    protected $fillable = [
        'name',
        'strength',
        'strength_derived',
        'strength_is_derived',
        'scientific_name',
        'name_ar',
        'manufacturer',
        'drug_class',
        'route',
        'price_egp',
        'price_captured_at',
        'source',
        'created_by',
        'uses_count',
    ];

    protected $casts = [
        'uses_count' => 'integer',
        'strength_is_derived' => 'boolean',
        'price_egp' => 'decimal:2',
        'price_captured_at' => 'date',
    ];

    /**
     * Columns a register may fill on a row that already exists.
     *
     * `name`, `strength`, `uses_count` and `created_by` are deliberately absent:
     * the first two are the row's identity, and the last two are the history of
     * what doctors actually did, which no import may rewrite.
     */
    public const ENRICHABLE = [
        'scientific_name', 'name_ar', 'manufacturer',
        'drug_class', 'route', 'price_egp', 'price_captured_at', 'source',
    ];

    /**
     * The dictionary as a reviewer's sheet.
     *
     * Owned by the model rather than by either writer: the console command and
     * the admin download both emit this, and two writers that could disagree
     * about the column order would hand back files the importer maps
     * differently. `id` leads because it is what makes a correction land on the
     * row it was made for.
     */
    public const SHEET_COLUMNS = [
        'id', 'name', 'strength', 'strength_derived', 'strength_is_derived',
        'scientific_name', 'manufacturer', 'drug_class', 'route',
        'price_egp', 'price_captured_at', 'uses_count', 'source',
    ];

    /** This row, in SHEET_COLUMNS order. */
    public function toSheetRow(): array
    {
        $row = [];

        foreach (self::SHEET_COLUMNS as $column) {
            $value = $this->{$column};

            $row[] = match ($column) {
                'strength_is_derived' => $value ? '1' : '',
                'price_captured_at' => optional($value)->toDateString(),
                default => $value,
            };
        }

        return $row;
    }

    /**
     * The typeahead a doctor sees, and the ONLY definition of it.
     *
     * Both the app endpoint and the admin preview call this, so the preview
     * cannot flatter the real thing: a screen that demonstrates a search the
     * doctor is not actually given is worse than no screen.
     *
     * Three axes, because a doctor reaches for a drug three ways: by the brand
     * he writes, by the ACTIVE INGREDIENT he was taught, and in Arabic.
     * Ingredient was the loud gap — «DICLOFENAC» returned nothing at all while
     * 102 registered products contain it.
     *
     * Ranking, in order:
     *
     *  1. names that START with what was typed — «AUGMENTIN» must put the
     *     Augmentins first, not a syrup that merely mentions it;
     *  2. names that merely contain it, because the register writes strength
     *     and pack into the name («… 1 GM 14 F.C.TABS.») and a doctor reaching
     *     for a dose types from the middle;
     *  3. an ingredient or Arabic-alias hit;
     *  4. then what has actually been prescribed most, then alphabetically —
     *     `uses_count` is why an imported row starts at zero.
     *
     * The Arabic alias is a MATCHING key only. It is a phonetic transliteration,
     * not a registered brand, so it never becomes the name of anything.
     */
    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query->orderByDesc('uses_count')->orderBy('name');
        }

        // Escaped, or a drug named with a literal % or _ would match everything.
        $like = addcslashes($term, '%_\\');
        $anywhere = '%' . $like . '%';

        // The alias is STORED folded, so the term has to be folded to meet it —
        // «اوجمنتين» and «أوجمنتين» are one word to everyone except a LIKE.
        $arabic = static::arabicKey($term);
        $arabicLike = $arabic === '' ? null : '%' . addcslashes($arabic, '%_\\') . '%';

        return $query
            ->where(fn ($q) => $q
                ->where('name', 'like', $anywhere)
                ->orWhere('scientific_name', 'like', $anywhere)
                ->when($arabicLike, fn ($w) => $w->orWhere('name_ar', 'like', $arabicLike)))
            ->orderByRaw(
                'CASE WHEN name LIKE ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END',
                [$like . '%', $anywhere]
            )
            ->orderByDesc('uses_count')
            ->orderBy('name');
    }

    /**
     * The Arabic alias reduced to what a search can compare.
     *
     * Two problems, one fixable. The fixable one: nobody types hamza
     * consistently — «اوجمنتين» and «أوجمنتين» are the same word to everyone
     * except a LIKE. Diacritics, tatweel, أإآ, ة/ه and ى/ي are folded, and the
     * latin punctuation the transliteration drags along («أوجمينتين . ./»)
     * is dropped.
     *
     * The one that is NOT fixable here: the source's Arabic is a mechanical
     * phonetic transliteration, so it spells «أوجمينتين» where a doctor writes
     * «أوجمنتين». Folding cannot close that, and guessing at it would be
     * inventing spellings for drugs. The Arabic axis is therefore a bonus, not
     * a promise — a real one needs registered Arabic names, which this register
     * does not carry.
     */
    public static function arabicKey(?string $value): string
    {
        $value = (string) $value;

        // Harakat and tatweel first, or folding letters would miss the ones
        // carrying a mark.
        $value = preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $value) ?? '';

        $value = strtr($value, [
            'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
            'ة' => 'ه', 'ى' => 'ي', 'ؤ' => 'و', 'ئ' => 'ي',
        ]);

        // Keep Arabic letters, digits and single spaces; everything else is the
        // English name's punctuation that came along for the ride.
        $value = preg_replace('/[^\x{0621}-\x{064A}0-9\s]/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /**
     * Why this row came back, for a screen that has to explain itself.
     *
     * A doctor who typed an ingredient and got twenty brand names deserves to
     * be told that is what happened, rather than left to guess.
     */
    public function matchedOn(string $term): string
    {
        $term = mb_strtolower(trim($term));

        if ($term === '') {
            return 'name';
        }

        foreach (['name' => $this->name, 'scientific' => $this->scientific_name] as $axis => $value) {
            if ($value !== null && str_contains(mb_strtolower((string) $value), $term)) {
                return $axis;
            }
        }

        $arabic = static::arabicKey($term);

        if ($arabic !== '' && str_contains((string) $this->name_ar, $arabic)) {
            return 'arabic';
        }

        return 'name';
    }

    /**
     * Record that a doctor wrote this drug: create it on first sight, and count
     * the use either way. Normalises blank strength to null so "500mg" and
     * "500mg " don't split into two rows. Returns the dictionary row.
     */
    public static function remember(string $name, ?string $strength, ?int $doctorId): self
    {
        $name = trim($name);
        $strength = trim((string) $strength);
        $strength = $strength === '' ? null : $strength;

        $medicine = static::query()->firstOrCreate(
            ['name' => $name, 'strength' => $strength],
            ['created_by' => $doctorId, 'uses_count' => 0],
        );

        $medicine->increment('uses_count');

        return $medicine;
    }
}
