<?php

namespace App\Http\Controllers\Business\Concerns;

use App\Models\CategoryPlatformService;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Shared scoping helpers for the business-owner panel: the logged-in owner's
 * id, their category_child, the services that child offers, and the item
 * types allowed for each (child, service). Keeps every owner screen consistent
 * and impossible to widen beyond the owner's own catalog.
 */
trait ResolvesOwnerCatalog
{
    protected function businessId(): int
    {
        return (int) Auth::id();
    }

    protected function childId(): int
    {
        return (int) (Auth::user()->category_child_id ?? 0);
    }

    /**
     * Services actually offered by the owner's category_child (active links).
     */
    protected function servicesForChild(): Collection
    {
        $childId = $this->childId();

        if ($childId <= 0) {
            return collect();
        }

        $serviceIds = CategoryPlatformService::query()
            ->where('child_id', $childId)
            ->where('is_active', 1)
            ->pluck('platform_service_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        if (empty($serviceIds)) {
            return collect();
        }

        return PlatformService::query()
            ->whereIn('id', $serviceIds)
            ->where('is_active', 1)
            ->orderBy('name_ar')
            ->orderBy('id')
            ->get(['id', 'key', 'name_ar', 'name_en', 'supports_deposit']);
    }

    /**
     * Item types the owner may use, keyed by service:
     * [serviceId => [['key','label'], ...]]. Restricted to the owner's child
     * via CategoryServiceConfig.allowed_item_types when configured.
     */
    protected function allowedTypesByService(Collection $services): array
    {
        $childId = $this->childId();
        $map = [];

        foreach ($services as $service) {
            $serviceId = (int) $service->id;

            $baseTypes = PlatformServiceItemType::query()
                ->where('platform_service_id', $serviceId)
                ->where('is_active', 1)
                ->ordered()
                ->get(['key', 'name_ar', 'name_en']);

            $restricted = CategoryServiceConfig::query()
                ->where('child_id', $childId)
                ->where('platform_service_id', $serviceId)
                ->where('is_active', 1)
                ->get()
                ->flatMap(function (CategoryServiceConfig $config) {
                    $data = is_array($config->config) ? $config->config : [];
                    return $data['allowed_item_types'] ?? [];
                })
                ->map(fn ($t) => trim((string) $t))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $map[$serviceId] = $baseTypes
                ->when(! empty($restricted), fn ($rows) => $rows->filter(fn ($r) => in_array((string) $r->key, $restricted, true)))
                ->map(fn (PlatformServiceItemType $r) => [
                    'key' => (string) $r->key,
                    'label' => $r->displayName('ar'),
                ])
                ->values()
                ->all();
        }

        return $map;
    }

    /**
     * Guard a posted (service, item_type) against the owner's own catalog.
     * Returns the validated service id, or aborts 422.
     */
    protected function assertAllowed(int $serviceId, string $itemType): void
    {
        $services = $this->servicesForChild();

        if (! $services->contains('id', $serviceId)) {
            abort(422, 'هذه الخدمة غير متاحة لنشاطك.');
        }

        $allowedKeys = array_column($this->allowedTypesByService($services)[$serviceId] ?? [], 'key');

        if (! in_array($itemType, $allowedKeys, true)) {
            abort(422, 'نوع العنصر غير مسموح لنشاطك مع هذه الخدمة.');
        }
    }
}
