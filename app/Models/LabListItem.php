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

    protected $fillable = ['list_id', 'source', 'source_id', 'sort_order'];

    protected $casts = [
        'source_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function list(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(LabList::class, 'list_id');
    }

    /** Sandbox table that holds this item's atom. */
    public static function sourceTable(string $source): string
    {
        return $source === self::SOURCE_ITEM_TYPE
            ? 'platform_service_item_types_new'
            : 'options_new';
    }

    /**
     * Resolve display names for a batch of items in two queries (one per source),
     * returning [ "option:26" => "ألفا روميو", "item_type:229" => "عيادة", ... ].
     */
    public static function resolveNames(iterable $items): array
    {
        $bySource = ['option' => [], 'item_type' => []];
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
