<?php

namespace App\Services\Catalog;

use App\Models\CategoryPlatformService;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformServiceItemType;
use Illuminate\Support\Facades\DB;

/**
 * The one place that writes what a category child may sell.
 *
 * Three admin screens answered the same question and wrote the same two rows
 * their own way: «مصفوفة كتالوج الخدمات», «ربط الخدمات بالتصنيفات (جماعي)» and
 * «طاولة عمل الابن». That is not merely untidy — the bulk screen rebuilt the
 * config from scratch on every save, so a key it did not know about was
 * dropped. `catalog_source`, `catalog_synced_at` and `notes` — the matrix's own
 * fields — survive on 20 of 1,055 configs. The rest were quietly erased by a
 * screen that had never heard of them.
 *
 * So the rules live here once:
 *
 *   1. **The link and the config are written together.** A config without an
 *      active `category_platform_services` row is unreachable — the owner panel
 *      and discovery both read the link — and a link without a config offers a
 *      service that can list nothing.
 *   2. **The config is MERGED over what is stored, never replaced.** A caller
 *      states the keys it knows; every other key keeps its value. Arrays like
 *      `allowed_item_types` are replaced wholesale, because a caller that names
 *      them is authoritative about them.
 *   3. **Item types are validated against the service's own active types**, in
 *      one place, so no screen can store a key the service does not have.
 *   4. **`meta` on the link is preserved** unless a caller explicitly replaces
 *      it. It carries provenance, and one screen passing `null` used to wipe
 *      what another had recorded.
 *
 * Callers stay responsible for their own transaction; every method here is a
 * single logical write and composes inside one.
 */
final class ChildServiceWriter
{
    /**
     * Offer this service to this (root, child) and state what it may list.
     *
     * @param  array<string,mixed>  $configPatch  keys to set; everything else is kept
     * @param  array<string,mixed>|null  $linkMeta  replaces the link's meta when given
     */
    public function enable(
        int $rootId,
        int $childId,
        int $serviceId,
        array $configPatch = [],
        ?int $sortOrder = null,
        ?array $linkMeta = null,
        ?string $source = null
    ): void {
        if ($rootId <= 0 || $childId <= 0 || $serviceId <= 0) {
            return;
        }

        $this->writeLink($rootId, $childId, $serviceId, true, $sortOrder, $linkMeta);
        $this->writeConfig($rootId, $childId, $serviceId, $configPatch, true, $sortOrder, $source);
    }

    /**
     * Stop offering it. Both rows are deactivated, never deleted: the config
     * holds the admin's work, and a child re-enabled next week should find its
     * item types where it left them.
     */
    public function disable(int $rootId, int $childId, int $serviceId): void
    {
        if ($rootId <= 0 || $childId <= 0 || $serviceId <= 0) {
            return;
        }

        CategoryPlatformService::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        CategoryServiceConfig::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->update(['is_active' => 0, 'updated_at' => now()]);
    }

    /** Deactivate every service of this (root, child) EXCEPT the ones given. */
    public function disableOthers(int $rootId, int $childId, array $keepServiceIds): void
    {
        $keep = collect($keepServiceIds)->map(fn ($id) => (int) $id)->filter()->values()->all();

        foreach ([CategoryPlatformService::class, CategoryServiceConfig::class] as $model) {
            $model::query()
                ->where('category_id', $rootId)
                ->where('child_id', $childId)
                ->when($keep !== [], fn ($query) => $query->whereNotIn('platform_service_id', $keep))
                ->update(['is_active' => 0, 'updated_at' => now()]);
        }
    }

    /** What is stored for this pair right now. */
    public function storedConfig(int $rootId, int $childId, int $serviceId): array
    {
        $stored = CategoryServiceConfig::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->value('config');

        if (is_array($stored)) {
            return $stored;
        }

        return json_decode((string) $stored, true) ?: [];
    }

    /**
     * What this child says about this service under any OTHER root.
     *
     * A child standing in two storefronts sells the same thing in both, so when
     * a screen switches a service on for a root that has never had it, the
     * honest starting point is what the child already says next door — not an
     * empty config, which is read everywhere as «every item type there is».
     *
     * Returns [] when the child has nothing to copy, and the caller must then
     * state the types itself.
     *
     * @return array<string,mixed>
     */
    public function configElsewhere(int $childId, int $serviceId, int $exceptRootId): array
    {
        $stored = CategoryServiceConfig::query()
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->where('category_id', '!=', $exceptRootId)
            ->orderByDesc('is_active')
            ->value('config');

        if (is_array($stored)) {
            return $stored;
        }

        return json_decode((string) $stored, true) ?: [];
    }

    /**
     * The item-type keys this service actually has, active. Cached per service
     * for the life of the request — a bulk save walks the same service across
     * hundreds of children.
     *
     * @return array<int,string>
     */
    public function allowedTypeKeys(int $serviceId): array
    {
        static $cache = [];

        return $cache[$serviceId] ??= PlatformServiceItemType::query()
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->pluck('key')
            ->map(fn ($key) => trim((string) $key))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function writeLink(
        int $rootId,
        int $childId,
        int $serviceId,
        bool $active,
        ?int $sortOrder,
        ?array $linkMeta
    ): void {
        $existing = CategoryPlatformService::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->first(['id', 'sort_order', 'meta']);

        $values = [
            'is_active' => $active ? 1 : 0,
            'updated_at' => now(),
        ];

        if ($sortOrder !== null) {
            $values['sort_order'] = $sortOrder;
        }

        // Only a caller that says something about meta may change it. Passing
        // null used to mean "wipe", which is how one screen erased the
        // provenance another had written.
        if ($linkMeta !== null) {
            $values['meta'] = $linkMeta;
        }

        if ($existing) {
            CategoryPlatformService::query()->where('id', $existing->id)->update($values);

            return;
        }

        CategoryPlatformService::query()->create($values + [
            'category_id' => $rootId,
            'child_id' => $childId,
            'platform_service_id' => $serviceId,
            'sort_order' => $sortOrder ?? 0,
        ]);
    }

    private function writeConfig(
        int $rootId,
        int $childId,
        int $serviceId,
        array $patch,
        bool $active,
        ?int $sortOrder,
        ?string $source
    ): void {
        $config = array_merge($this->storedConfig($rootId, $childId, $serviceId), $patch);

        if (array_key_exists('allowed_item_types', $config)) {
            $allowed = $this->allowedTypeKeys($serviceId);

            $config['allowed_item_types'] = collect($config['allowed_item_types'])
                ->map(fn ($key) => trim((string) $key))
                ->filter(fn ($key) => in_array($key, $allowed, true))
                ->unique()
                ->values()
                ->all();
        }

        if ($source !== null) {
            $config['config_source'] = $source;
            $config['config_updated_at'] = now()->toDateTimeString();
        }

        $values = [
            'config' => $config,
            'is_active' => $active ? 1 : 0,
            'updated_at' => now(),
        ];

        if ($sortOrder !== null) {
            $values['sort_order'] = $sortOrder;
        }

        $existingId = CategoryServiceConfig::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->value('id');

        if ($existingId) {
            CategoryServiceConfig::query()->where('id', $existingId)->update($values);

            return;
        }

        CategoryServiceConfig::query()->create($values + [
            'category_id' => $rootId,
            'child_id' => $childId,
            'platform_service_id' => $serviceId,
            'sort_order' => $sortOrder ?? 0,
        ]);
    }
}
