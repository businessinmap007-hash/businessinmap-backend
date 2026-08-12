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
        'created_by',
        'uses_count',
    ];

    protected $casts = [
        'uses_count' => 'integer',
    ];

    /**
     * The typeahead a doctor sees, and the ONLY definition of it.
     *
     * Both the app endpoint and the admin preview call this, so the preview
     * cannot flatter the real thing: a screen that demonstrates a search the
     * doctor is not actually given is worse than no screen.
     *
     * Ranking, in order:
     *
     *  1. names that START with what was typed — «AUGMENTIN» must put the
     *     Augmentins first, not a syrup that merely mentions it;
     *  2. names that merely contain it, because the register writes strength
     *     and pack into the name («… 1 GM 14 F.C.TABS.») and a doctor reaching
     *     for a dose types from the middle;
     *  3. then what has actually been prescribed most, then alphabetically —
     *     `uses_count` is why an imported row starts at zero.
     */
    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query->orderByDesc('uses_count')->orderBy('name');
        }

        // Escaped, or a drug named with a literal % or _ would match everything.
        $like = addcslashes($term, '%_\\');

        return $query
            ->where('name', 'like', '%' . $like . '%')
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$like . '%'])
            ->orderByDesc('uses_count')
            ->orderBy('name');
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
