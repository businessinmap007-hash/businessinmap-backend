<?php

namespace App\Services\Menu;

use App\Models\MenuItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The values a merchant states for ONE unit — a car's year, mileage, gearbox
 * and colour — as opposed to the catalog master's own specs. Which attributes
 * a unit may carry is its detail kind's `per_item` fields (MenuDetailProfile);
 * anything else is refused. Read back as the same `{code,name,value}` rows
 * ProductSpecs gives, so the product page and the card show one list.
 */
class MenuItemAttributes
{
    /** @var array<int, list<array<string,mixed>>> item id => rows, filled by preload() */
    private array $cache = [];

    /**
     * Read many items in one query pair — the public menu draws dozens.
     *
     * @param  list<int>  $itemIds
     */
    public function preload(array $itemIds): void
    {
        $itemIds = array_values(array_diff(array_unique(array_map('intval', $itemIds)), array_keys($this->cache)));
        if ($itemIds === []) {
            return;
        }

        $this->cache += array_fill_keys($itemIds, []);

        $english = app()->getLocale() === 'en';
        $pick = fn ($ar, $en) => trim((string) ($english ? ($en ?: $ar) : ($ar ?: $en)));

        $rows = DB::table('menu_item_attribute_values as v')
            ->join('catalog_attributes as a', 'a.id', '=', 'v.attribute_id')
            ->leftJoin('catalog_units as u', 'u.id', '=', 'a.unit_id')
            ->leftJoin('catalog_attribute_options as o', 'o.id', '=', 'v.option_id')
            ->whereIn('v.menu_item_id', $itemIds)
            ->orderBy('a.sort_order')->orderBy('a.id')
            ->get([
                'v.menu_item_id', 'v.attribute_id', 'v.option_id', 'v.value_number', 'v.value_text',
                'a.code', 'a.name_ar', 'a.name_en', 'a.data_type',
                'u.name_ar as unit_ar', 'u.name_en as unit_en', 'o.value_ar as option_ar', 'o.value_en as option_en',
            ]);

        foreach ($rows as $r) {
            if ($r->option_id) {
                $value = $pick($r->option_ar, $r->option_en);
            } elseif ($r->value_number !== null) {
                $number = rtrim(rtrim(number_format((float) $r->value_number, 4, '.', ''), '0'), '.');
                $value = trim($number . ' ' . $pick($r->unit_ar, $r->unit_en));
            } else {
                $value = trim((string) $r->value_text);
            }

            if ($value === '') {
                continue;
            }

            $this->cache[(int) $r->menu_item_id][] = [
                'attribute_id' => (int) $r->attribute_id,
                'code' => (string) $r->code,
                'name' => $pick($r->name_ar, $r->name_en),
                'value' => $value,
                'option_id' => $r->option_id ? (int) $r->option_id : null,
                'number' => $r->value_number !== null ? (float) $r->value_number : null,
                'text' => $r->value_text,
            ];
        }
    }

    /** @return list<array<string,mixed>> */
    public function forItem(int $itemId): array
    {
        $this->preload([$itemId]);

        return $this->cache[$itemId] ?? [];
    }

    /**
     * The unit's values laid over its master's specs: the unit wins where both
     * state the same attribute (the master has no year; a unit that says
     * «٢٠٢٣» is not contradicted by it).
     *
     * @param  list<array{code:string,name:string,value:string}>  $masterSpecs
     * @return list<array{code:string,name:string,value:string}>
     */
    public function mergeIntoSpecs(array $masterSpecs, int $itemId): array
    {
        $own = $this->forItem($itemId);
        if ($own === []) {
            return $masterSpecs;
        }

        $codes = array_column($own, 'code');
        $kept = array_values(array_filter($masterSpecs, fn ($s) => ! in_array($s['code'], $codes, true)));

        foreach ($own as $row) {
            $kept[] = ['code' => $row['code'], 'name' => $row['name'], 'value' => $row['value']];
        }

        return $kept;
    }

    /**
     * Store what the merchant stated. Only the item's detail kind's `per_item`
     * fields are accepted; a blank value removes the row. Values that do not
     * fit their attribute (a word for the year, an option of another
     * attribute) are a 422 — unlike a stray id, a wrong value is a typo the
     * merchant must see.
     *
     * @param  array<int|string,mixed>  $input  attribute id => value
     * @param  list<array<string,mixed>>  $allowedFields  the kind's fields with `per_item`
     */
    public function sync(MenuItem $item, array $input, array $allowedFields): void
    {
        $allowed = collect($allowedFields)->where('per_item', true)->keyBy('id');
        $errors = [];
        $writes = [];
        $deletes = [];

        foreach ($input as $attributeId => $raw) {
            $field = $allowed->get((int) $attributeId);
            if (! $field) {
                continue;
            }

            $key = "attributes.{$attributeId}";
            $blank = $raw === null || (is_string($raw) && trim($raw) === '');

            if ($blank) {
                $deletes[] = (int) $attributeId;
                continue;
            }

            $row = ['option_id' => null, 'value_number' => null, 'value_text' => null];

            if ($field['data_type'] === 'select') {
                $ok = is_numeric($raw) && DB::table('catalog_attribute_options')
                    ->where('id', (int) $raw)->where('attribute_id', (int) $attributeId)->where('is_active', 1)->exists();
                if (! $ok) {
                    $errors[$key] = __('القيمة المختارة لا تخص هذا الحقل.');
                    continue;
                }
                $row['option_id'] = (int) $raw;
            } elseif ($field['data_type'] === 'number') {
                $normalized = str_replace(['٫', ','], ['.', ''], (string) $raw);
                if (! is_numeric($normalized) || (float) $normalized < 0 || (float) $normalized > 999999999) {
                    $errors[$key] = __('«:name» رقم صحيح أو عشري.', ['name' => $field['name']]);
                    continue;
                }
                $row['value_number'] = (float) $normalized;
            } else {
                $row['value_text'] = mb_substr(trim((string) $raw), 0, 190);
            }

            $writes[(int) $attributeId] = $row;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($item, $writes, $deletes) {
            $now = now();

            if ($deletes !== []) {
                DB::table('menu_item_attribute_values')
                    ->where('menu_item_id', $item->id)->whereIn('attribute_id', $deletes)->delete();
            }

            foreach ($writes as $attributeId => $row) {
                DB::table('menu_item_attribute_values')->updateOrInsert(
                    ['menu_item_id' => $item->id, 'attribute_id' => $attributeId],
                    $row + ['updated_at' => $now, 'created_at' => $now]
                );
            }
        });

        unset($this->cache[$item->id]);
    }
}
