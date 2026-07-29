<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One atom placed into a lab list. `source` is 'option' (→ options_new) or
 * 'item_type' (→ platform_service_item_types_new); `source_id` is its id there.
 * The atom's display name is resolved on demand from the sandbox source table.
 */
class LabListItem extends Model
{
    public const SOURCE_OPTION = 'option';
    public const SOURCE_ITEM_TYPE = 'item_type';
    public const SOURCE_CATEGORY_CHILD = 'category_child';

    /** All valid sources → the sandbox/reference table each resolves against. */
    public const SOURCES = [
        self::SOURCE_ITEM_TYPE => 'platform_service_item_types_new',
        self::SOURCE_OPTION => 'options_new',
        self::SOURCE_CATEGORY_CHILD => 'category_children_master',
    ];

    /** Human label per source (Arabic). */
    public const SOURCE_LABELS = [
        self::SOURCE_ITEM_TYPE => 'نوع عنصر',
        self::SOURCE_OPTION => 'خيار',
        self::SOURCE_CATEGORY_CHILD => 'تخصص',
    ];

    protected $fillable = ['list_id', 'source', 'source_id', 'sort_order'];

    protected $casts = [
        'source_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function list(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LabList::class, 'list_id');
    }

    /** Sandbox/reference table that holds this item's atom. */
    public static function sourceTable(string $source): string
    {
        return self::SOURCES[$source] ?? 'options_new';
    }

    public static function label(string $source): string
    {
        return self::SOURCE_LABELS[$source] ?? $source;
    }

    /**
     * Resolve display names for a batch of items in one query per source,
     * returning [ "option:26" => "ألفا روميو", "item_type:229" => "عيادة", ... ].
     */
    public static function resolveNames(iterable $items): array
    {
        $bySource = array_fill_keys(array_keys(self::SOURCES), []);
        foreach ($items as $it) {
            $bySource[$it->source][] = (int) $it->source_id;
        }

        $names = [];
        foreach ($bySource as $source => $ids) {
            if ($ids === []) {
                continue;
            }
            $rows = DB::table(self::sourceTable($source))
                ->whereIn('id', array_unique($ids))
                ->get(['id', 'name_ar', 'name_en']);
            foreach ($rows as $r) {
                $names["{$source}:{$r->id}"] = (string) ($r->name_ar ?: $r->name_en ?: "#{$r->id}");
            }
        }

        return $names;
    }
}
