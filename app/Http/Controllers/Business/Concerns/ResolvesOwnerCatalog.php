<?php

namespace App\Http\Controllers\Business\Concerns;

use App\Models\CategoryPlatformService;
use App\Models\CategoryServiceConfig;
use App\Models\PlatformService;
use App\Models\User;
use App\Models\PlatformServiceItemType;
use App\Support\BusinessContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Shared scoping helpers for the business-owner panel: the logged-in owner's
 * id, their category_child, the services that child offers, and the item
 * types allowed for each (child, service). Keeps every owner screen consistent
 * and impossible to widen beyond the owner's own catalog.
 *
 * Everything hangs off the ACTING business — the owner, or, when a request went
 * through the `business.member` middleware, the employer a delegated staff
 * member is acting for. Without that middleware (the web panel, owner-only API
 * routes) it falls back to the authenticated user, so behavior is unchanged.
 */
trait ResolvesOwnerCatalog
{
    /** The business being managed (owner, or a delegate's employer). */
    protected function actingBusiness(): ?User
    {
        return BusinessContext::business(request());
    }

    protected function businessId(): int
    {
        return (int) (optional($this->actingBusiness())->id ?: Auth::id());
    }

    protected function childId(): int
    {
        return (int) (optional($this->actingBusiness())->category_child_id ?? 0);
    }

    /**
     * The root the business sits under. A child is shared across roots and no
     * longer answers the same questions under each, so anything reading the
     * option catalogue needs this too — see CategoryChildOptionScope.
     */
    protected function rootId(): int
    {
        return (int) (optional($this->actingBusiness())->category_id ?? 0);
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
                // `meta` carries the kind's granularity — a stay is counted in
                // days, a clinic slot in minutes — so the panel can draw a date
                // range where a range is meant and a time slot where it is not.
                ->get(['key', 'name_ar', 'name_en', 'meta']);

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
                ->map(fn (PlatformServiceItemType $r) => array_filter([
                    'key' => (string) $r->key,
                    'label' => $r->displayName('ar'),
                    'granularity' => $r->granularity(),
                ], fn ($value) => $value !== null))
                ->values()
                ->all();
        }

        return $map;
    }

    /**
     * The kinds this owner may say a unit IS, grouped by option group.
     *
     * Same source as the pricing screen — MerchantOfferingVocabulary — on
     * purpose: a unit whose kind is not priceable could never point at a price,
     * and two lists that drift are worse than one that is long.
     *
     * @return \Illuminate\Support\Collection<string,\Illuminate\Support\Collection>
     */
    protected function lineOptionsForUnits(): Collection
    {
        // ما أعلنه التاجرُ أساسًا للسعر — وإن لم يُعلن، فكلُّ ما يصلح سطرًا.
        return app(\App\Services\BookingVocabularyRoles::class)->only(
            app(\App\Services\MerchantOfferingVocabulary::class)
                ->for($this->businessId(), $this->childId(), $this->rootId())['lines'],
            $this->businessId(),
            \App\Services\BookingVocabularyRoles::ROLE_LINE
        );
    }

    /**
     * Guard a posted line option the same way the pricing screen guards it: it
     * must be in this merchant's vocabulary AND play the `line` role. Anything
     * else becomes «no kind stated» rather than an error, because the column is
     * nullable by design — a clinic never needs it.
     */
    protected function sanitizeLineOption(?int $optionId): ?int
    {
        $optionId = (int) $optionId;

        if ($optionId <= 0) {
            return null;
        }

        $lines = app(\App\Services\MerchantOfferingVocabulary::class)
            ->pickableIds($this->businessId(), $this->childId(), $this->rootId())['lines'];

        return $lines->contains($optionId) ? $optionId : null;
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
