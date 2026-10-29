<?php

namespace App\Services\Inventory;

use App\Models\BusinessCatalogListing;
use App\Models\Inventory\Item;
use App\Models\Inventory\RecipeLine;
use App\Models\MenuItem;
use App\Models\MenuItemExtra;
use App\Models\MenuItemVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What one sale takes out of the stock. A menu item or a retail listing owns its lines — one line for a can sold as a can
 * («ربط مباشر»), several for a burger (bread, meat, cheese…). A size (variant) and an extra own lines of their own that ADD
 * to the item's: «كبير» adds 100 g of fries, «إضافة جبنة» one slice. A line's waste % is what preparation loses: 180 g
 * of meat at 8% takes 195.65 g out of the stock.
 */
final class RecipeService
{
    public const MENU_ITEM = 'menu_item';
    public const MENU_VARIANT = 'menu_variant';
    public const MENU_EXTRA = 'menu_extra';
    public const RETAIL_LISTING = 'retail_listing';

    public const OWNERS = [self::MENU_ITEM, self::MENU_VARIANT, self::MENU_EXTRA, self::RETAIL_LISTING];

    public static function effective(float $qty, float $wastePct): float
    {
        $wastePct = min(max($wastePct, 0), 95);

        return $qty / (1 - $wastePct / 100);
    }

    /** The owner must belong to this business; refused as «not found» otherwise. */
    public function assertOwner(int $businessId, string $ownerType, int $ownerId): void
    {
        $belongs = match ($ownerType) {
            self::MENU_ITEM => MenuItem::query()->where('business_id', $businessId)->whereKey($ownerId)->exists(),
            self::MENU_VARIANT => MenuItemVariant::query()->whereKey($ownerId)
                ->whereHas('menuItem', fn ($q) => $q->where('business_id', $businessId))->exists(),
            self::MENU_EXTRA => MenuItemExtra::query()->whereKey($ownerId)
                ->whereHas('menuItem', fn ($q) => $q->where('business_id', $businessId))->exists(),
            self::RETAIL_LISTING => BusinessCatalogListing::query()->where('business_id', $businessId)->whereKey($ownerId)->exists(),
            default => false,
        };

        if (! $belongs) {
            throw ValidationException::withMessages(['owner_id' => __('الصنف المطلوب ربطه غير موجود.')]);
        }
    }

    /**
     * Replace the owner's lines.
     *
     * @param  list<array{item_id:int,qty:float,waste_pct?:float}>  $lines
     */
    public function setLines(int $businessId, string $ownerType, int $ownerId, array $lines): Collection
    {
        $this->assertOwner($businessId, $ownerType, $ownerId);

        $itemIds = array_map(fn ($l) => (int) $l['item_id'], $lines);
        $known = Item::query()->where('business_id', $businessId)->whereIn('id', $itemIds)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count(array_diff($itemIds, $known)) > 0) {
            throw ValidationException::withMessages(['lines' => __('أحد المكونات ليس صنفًا في مخزونك.')]);
        }

        DB::transaction(function () use ($businessId, $ownerType, $ownerId, $lines) {
            RecipeLine::query()->where('owner_type', $ownerType)->where('owner_id', $ownerId)->delete();

            foreach ($lines as $line) {
                RecipeLine::create([
                    'business_id' => $businessId,
                    'owner_type' => $ownerType,
                    'owner_id' => $ownerId,
                    'item_id' => (int) $line['item_id'],
                    'qty' => (float) $line['qty'],
                    'waste_pct' => (float) ($line['waste_pct'] ?? 0),
                ]);
            }
        });

        if ($itemIds !== []) {
            app(AvailabilitySync::class)->refresh($businessId, $itemIds);
        }

        return $this->lines($ownerType, $ownerId);
    }

    public function lines(string $ownerType, int $ownerId): Collection
    {
        return RecipeLine::query()->where('owner_type', $ownerType)->where('owner_id', $ownerId)->orderBy('id')->get();
    }

    /**
     * Base-unit quantities one sold line takes: item_id => qty.
     *
     * @param  list<array{id:int,qty?:int}>  $extras  menu extras chosen on the line (each with its own count)
     * @return array<int,float>
     */
    public function consumption(string $ownerType, int $ownerId, float $qty, ?int $variantId = null, array $extras = []): array
    {
        $need = [];
        $add = function (string $type, int $id, float $times) use (&$need) {
            foreach ($this->lines($type, $id) as $line) {
                $need[(int) $line->item_id] = ($need[(int) $line->item_id] ?? 0) + self::effective((float) $line->qty, (float) $line->waste_pct) * $times;
            }
        };

        $add($ownerType, $ownerId, $qty);

        if ($ownerType === self::MENU_ITEM) {
            if ($variantId) {
                $add(self::MENU_VARIANT, $variantId, $qty);
            }
            foreach ($extras as $extra) {
                if ((int) ($extra['id'] ?? 0) > 0) {
                    $add(self::MENU_EXTRA, (int) $extra['id'], $qty * max(1, (int) ($extra['qty'] ?? 1)));
                }
            }
        }

        return array_map(fn ($q) => round($q, 4), $need);
    }

    /** Cost of one unit of the owner by the current average costs (the canvas's «تكلفة الوصفة»). */
    public function cost(string $ownerType, int $ownerId): float
    {
        $total = 0.0;

        foreach ($this->lines($ownerType, $ownerId) as $line) {
            $avg = (float) Item::query()->whereKey($line->item_id)->value('avg_cost');
            $total += self::effective((float) $line->qty, (float) $line->waste_pct) * $avg;
        }

        return round($total, 2);
    }

    public static function ownerFor(string $offeringType): ?string
    {
        return match ($offeringType) {
            MenuItem::class => self::MENU_ITEM,
            BusinessCatalogListing::class => self::RETAIL_LISTING,
            default => null,
        };
    }
}
