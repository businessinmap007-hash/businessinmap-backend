<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Setting;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Who may use the inventory suite, and which parts of it.
 *
 * «ابنى نواه تصلح للكل وتظهر الحقول بناء على التصنيفات كما فعلنا فى الخدمات» (owner, 2026-10-29): the features are the item
 * types of the `inventory` platform service, ticked per (root, child) like every other service. An empty list means every
 * feature, as for every service. `optional_item_types` in the same config start OFF for a business until it switches them
 * on; the business's own choice is kept as the list it switched OFF (`inv_settings.features_off`).
 */
final class InventoryAccess
{
    public const SERVICE_KEY = 'inventory';

    public const UNITS = 'units';
    public const SALES_LINK = 'sales_link';
    public const RECIPES = 'recipes';
    public const LOTS = 'lots_expiry';
    public const BARCODE = 'barcode';
    public const MULTI_WAREHOUSE = 'multi_warehouse';
    public const WASTE = 'waste';
    public const PURCHASES = 'purchases';
    public const POS = 'pos';

    /** feature => [ar, en], in display order */
    public const FEATURES = [
        self::UNITS => ['الأصناف والوحدات', 'Items and units'],
        self::SALES_LINK => ['الربط بالمنيو والمتجر', 'Link to menu and store'],
        self::RECIPES => ['الوصفات (المكونات)', 'Recipes (ingredients)'],
        self::LOTS => ['التشغيلات وتاريخ الصلاحية', 'Lots and expiry dates'],
        self::BARCODE => ['الباركود', 'Barcode'],
        self::MULTI_WAREHOUSE => ['أكثر من مخزن', 'Several warehouses'],
        self::WASTE => ['الهالك والتالف', 'Waste and damage'],
        self::PURCHASES => ['المشتريات والموردون', 'Purchases and suppliers'],
        self::POS => ['الكاشير', 'Cashier'],
    ];

    /**
     * What the business's category allows, or null when the inventory service is not offered to it at all.
     *
     * @return array{allowed:list<string>,optional:list<string>}|null
     */
    public function categoryFeatures(User $business): ?array
    {
        $serviceId = (int) DB::table('platform_services')->where('key', self::SERVICE_KEY)->where('is_active', 1)->value('id');
        $childId = (int) ($business->category_child_id ?? 0);
        $rootId = (int) ($business->category_id ?? 0);

        if ($serviceId <= 0 || $childId <= 0) {
            return null;
        }

        $link = DB::table('category_platform_services')
            ->where('platform_service_id', $serviceId)->where('child_id', $childId)->where('is_active', 1)
            ->when($rootId > 0, fn ($q) => $q->where('category_id', $rootId))
            ->exists();

        if (! $link) {
            return null;
        }

        $config = (array) json_decode((string) DB::table('category_service_configs')
            ->where('platform_service_id', $serviceId)->where('child_id', $childId)->where('is_active', 1)
            ->when($rootId > 0, fn ($q) => $q->where('category_id', $rootId))
            ->value('config'), true);

        $allowed = array_values(array_intersect(array_keys(self::FEATURES), (array) ($config['allowed_item_types'] ?? [])));
        $allowed = $allowed === [] ? array_keys(self::FEATURES) : $allowed;

        if (! in_array(self::UNITS, $allowed, true)) {
            array_unshift($allowed, self::UNITS);
        }

        $optional = array_values(array_intersect($allowed, (array) ($config['optional_item_types'] ?? [])));

        return ['allowed' => $allowed, 'optional' => array_values(array_diff($optional, [self::UNITS]))];
    }

    public function settings(int $businessId): Setting
    {
        return Setting::query()->firstOrCreate(['business_id' => $businessId]);
    }

    public function available(User $business): bool
    {
        return $this->categoryFeatures($business) !== null;
    }

    public function enabled(User $business): bool
    {
        return $this->available($business) && (bool) $this->settings((int) $business->id)->enabled;
    }

    /** @return list<string> the features this business works with now */
    public function features(User $business): array
    {
        $category = $this->categoryFeatures($business);

        if ($category === null) {
            return [];
        }

        $off = $this->settings((int) $business->id)->features_off;
        $off = is_array($off) ? $off : $category['optional'];

        return array_values(array_diff($category['allowed'], array_diff($off, [self::UNITS])));
    }

    public function has(User $business, string $feature): bool
    {
        return in_array($feature, $this->features($business), true);
    }

    public function assertFeature(User $business, string $feature): void
    {
        if (! $this->has($business, $feature)) {
            throw ValidationException::withMessages(['feature' => __('هذا الجزء غير مفعّل لنشاطك: :feature', ['feature' => self::FEATURES[$feature][0] ?? $feature])]);
        }
    }

    /** The business's switch of its optional features: everything allowed and not listed here is turned off. */
    public function setFeatures(User $business, array $on): void
    {
        $category = $this->categoryFeatures($business) ?? ['allowed' => [], 'optional' => []];
        $on = array_values(array_intersect($category['allowed'], $on));
        $this->settings((int) $business->id)->update(['features_off' => array_values(array_diff($category['allowed'], $on, [self::UNITS]))]);
    }

    public function defaultWarehouse(int $businessId): Warehouse
    {
        $warehouse = Warehouse::query()->where('business_id', $businessId)->where('is_active', 1)->orderByDesc('is_default')->orderBy('id')->first();

        return $warehouse ?? Warehouse::create([
            'uuid' => (string) Str::uuid(),
            'business_id' => $businessId,
            'name' => __('المخزن الرئيسي'),
            'is_default' => true,
        ]);
    }

    /** A warehouse of THIS business (404-style refusal for anyone else's id). */
    public function warehouse(int $businessId, ?int $warehouseId): Warehouse
    {
        if (! $warehouseId) {
            return $this->defaultWarehouse($businessId);
        }

        $warehouse = Warehouse::query()->where('business_id', $businessId)->find($warehouseId);

        if (! $warehouse) {
            throw ValidationException::withMessages(['warehouse_id' => __('المخزن غير موجود.')]);
        }

        return $warehouse;
    }
}
