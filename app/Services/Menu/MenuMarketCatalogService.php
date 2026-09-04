<?php

namespace App\Services\Menu;

use App\Models\BusinessMenuSetting;
use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OfferingOption;
use App\Services\MerchantOfferingVocabulary;
use Illuminate\Support\Facades\DB;

/**
 * The shared logic behind «تعبئة الرفوف» — a supermarket/greengrocer/etc. fills
 * its menu from the platform's own product vocabulary instead of typing one
 * item at a time. Used by both the web business panel
 * ({@see \App\Http\Controllers\Business\MenuMarketCatalogController}) and the
 * bim_app API ({@see \App\Http\Controllers\Api\V2\MenuMarketCatalogController})
 * — one place owns the business rules (the default-margin auto-price, "a blank
 * price clears the row", vocabulary scoping) so the two surfaces cannot drift.
 */
class MenuMarketCatalogService
{
    public function __construct(private readonly MerchantOfferingVocabulary $vocabulary)
    {
    }

    /**
     * The web business panel's own shape: `name` is Arabic-only (the panel
     * has no locale switch) and `item` is the live MenuItem model, because the
     * Blade view reads it as an object (`$item->available_quantity`, etc.).
     *
     * @return array<int,array{name:string,rows:array<int,array{option_id:int,name:string,item:MenuItem|null}>,filled:int}>
     */
    public function rawGroups(int $businessId, int $childId, int $rootId): array
    {
        $lines = $this->vocabulary->for($businessId, $childId, $rootId)['lines'];
        $existing = $this->existingByOption($businessId);

        $groups = [];

        foreach ($lines as $groupName => $options) {
            $rows = [];

            foreach ($options as $option) {
                $item = $existing->get((int) $option->id);

                $rows[] = [
                    'option_id' => (int) $option->id,
                    'name' => (string) ($option->name_ar ?: $option->name_en),
                    'item' => $item,
                ];
            }

            $groups[] = [
                'name' => (string) $groupName,
                'rows' => $rows,
                'filled' => count(array_filter($rows, fn ($r) => $r['item'] !== null)),
            ];
        }

        return $groups;
    }

    /**
     * The bilingual, JSON-friendly shape for the API — everything from
     * {@see rawGroups()}, with both languages and a plain array per item
     * instead of the model.
     *
     * @return array<int,array{group_id:int,name_ar:string,name_en:string,rows:array<int,array<string,mixed>>,filled:int,total:int}>
     */
    public function groups(int $businessId, int $childId, int $rootId): array
    {
        $lines = $this->vocabulary->for($businessId, $childId, $rootId)['lines'];
        $existing = $this->existingByOption($businessId);
        $groupNamesEn = DB::table('option_groups')->pluck('name_en', 'id');

        $groups = [];

        foreach ($lines as $groupName => $options) {
            $rows = [];
            $groupId = 0;

            foreach ($options as $option) {
                $groupId = $groupId ?: (int) $option->group_id;
                $item = $existing->get((int) $option->id);

                $rows[] = [
                    'option_id' => (int) $option->id,
                    'name_ar' => (string) $option->name_ar,
                    'name_en' => (string) ($option->name_en ?: $option->name_ar),
                    'item' => $item ? $this->itemPayload($item) : null,
                ];
            }

            $groups[] = [
                'group_id' => $groupId,
                'name_ar' => (string) $groupName,
                'name_en' => (string) ($groupNamesEn[$groupId] ?? $groupName),
                'rows' => $rows,
                'filled' => count(array_filter($rows, fn ($r) => $r['item'] !== null)),
                'total' => count($rows),
            ];
        }

        return $groups;
    }

    /** @return array{saved:int,cleared:int} */
    public function save(int $businessId, int $childId, int $rootId, array $rows): array
    {
        $picks = $this->vocabulary->pickableIds($businessId, $childId, $rootId)['lines'];
        $existing = $this->existingByOption($businessId);

        $marginPercent = BusinessMenuSetting::query()->where('business_id', $businessId)->value('default_margin_percent');
        $marginPercent = $marginPercent !== null ? (float) $marginPercent : null;

        $saved = 0;
        $cleared = 0;

        DB::transaction(function () use ($rows, $picks, $existing, $businessId, $marginPercent, &$saved, &$cleared) {
            foreach ($rows as $optionId => $data) {
                $optionId = (int) $optionId;

                if (! $picks->contains($optionId)) {
                    continue;
                }

                $item = $existing->get($optionId);

                $price = ($data['base_price'] ?? '') !== '' ? round((float) $data['base_price'], 2) : null;
                $supply = ($data['supply_price'] ?? '') !== '' ? round((float) $data['supply_price'], 2) : null;
                $qty = ($data['quantity'] ?? '') !== '' ? max(0, (int) $data['quantity']) : null;
                $unit = trim((string) ($data['sale_unit'] ?? '')) ?: null;
                $brand = trim((string) ($data['brand_name'] ?? '')) ?: null;

                if ($price === null && $supply !== null && $marginPercent !== null) {
                    $price = round($supply * (1 + $marginPercent / 100), 2);
                }

                if ($price === null) {
                    if ($item && $item->is_active) {
                        $item->update(['is_active' => false]);
                        $cleared++;
                    }

                    continue;
                }

                if (! $item) {
                    $option = Option::find($optionId);

                    if (! $option) {
                        continue;
                    }

                    $item = new MenuItem([
                        'business_id' => $businessId,
                        'name_ar' => $option->name_ar,
                        'name_en' => $option->name_en,
                        'sort_order' => 0,
                    ]);
                }

                $item->fill([
                    'business_id' => $businessId,
                    'base_price' => $price,
                    'supply_price' => $supply,
                    'sale_unit' => in_array($unit, \App\Support\SaleUnits::codes(), true) ? $unit : null,
                    'brand_name' => $brand,
                    'available_quantity' => $qty,
                    'is_active' => true,
                ]);
                $item->save();
                $item->syncOfferingOptions($optionId, $item->modifierOptions()->pluck('id')->all(), $item->currentOfferingAdjustments());

                $saved++;
            }
        });

        return ['saved' => $saved, 'cleared' => $cleared];
    }

    /** @return \Illuminate\Support\Collection<int,MenuItem> keyed by the option id it prices */
    public function existingByOption(int $businessId)
    {
        $ids = DB::table('offering_options as oo')
            ->join('menu_items as m', 'm.id', '=', 'oo.offering_id')
            ->where('oo.offering_type', (new MenuItem())->getMorphClass())
            ->where('oo.role', OfferingOption::ROLE_LINE)
            ->where('m.business_id', $businessId)
            ->pluck('m.id', 'oo.option_id');

        if ($ids->isEmpty()) {
            return collect();
        }

        $items = MenuItem::query()
            ->whereIn('id', $ids->values())
            ->with('offeringOptions.option')
            ->get()
            ->keyBy('id');

        return $ids->map(fn ($menuItemId) => $items->get($menuItemId))->filter();
    }

    /** @return array<string,mixed> */
    private function itemPayload(MenuItem $item): array
    {
        return [
            'id' => $item->id,
            'base_price' => $item->base_price !== null ? (float) $item->base_price : null,
            'supply_price' => $item->supply_price !== null ? (float) $item->supply_price : null,
            'sale_unit' => $item->sale_unit,
            'brand_name' => $item->brand_name,
            'available_quantity' => $item->available_quantity,
            'is_active' => (bool) $item->is_active,
        ];
    }
}
