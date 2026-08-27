<?php

namespace App\Services\Retail;

use App\Models\PlatformService;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The "منتجاتي" screen has worked end to end since 2026-08-04 and the shared
 * catalog master has carried real, pickable stock since 2026-08-13, but
 * nothing ever told an eligible merchant either was true — a 2026-08-28
 * investigation found zero `business_catalog_listings` despite 1,295 catalog
 * products and 172 active retail configs. This nudges each qualifying
 * business exactly once, ever: the dedup key IS the existence of a prior
 * `retail_catalog_ready` notification, not a table of its own.
 */
class RetailCatalogNudgeService
{
    public const EVENT_KEY = 'retail_catalog_ready';

    /** @return array{scanned:int,notified:int} */
    public function run(int $limit = 200): array
    {
        $businessIds = $this->candidates($limit);
        $notified = 0;

        foreach ($businessIds as $businessId) {
            if ($this->notifyIfEligible((int) $businessId)) {
                $notified++;
            }
        }

        return ['scanned' => $businessIds->count(), 'notified' => $notified];
    }

    /**
     * Notify one business if it genuinely qualifies right now: never listed a
     * product, never nudged before, retail still actively linked to its
     * child, and its allowed types actually have stock. Safe to call directly
     * for a single business (e.g. right after its retail link goes active),
     * not only from the scheduled sweep.
     */
    public function notifyIfEligible(int $businessId): bool
    {
        $business = DB::table('users')->where('id', $businessId)->where('type', 'business')->first();

        if (! $business || (int) $business->category_child_id <= 0) {
            return false;
        }

        if ($this->alreadyNotified($businessId) || $this->hasAnyListing($businessId)) {
            return false;
        }

        $serviceId = $this->retailServiceId();
        $childId = (int) $business->category_child_id;

        if ($serviceId <= 0 || ! $this->hasActiveLink($childId, $serviceId)) {
            return false;
        }

        $types = $this->allowedTypeKeys($childId, $serviceId);

        if (empty($types) || ! $this->hasStock($types)) {
            return false;
        }

        app(NotificationDispatcherService::class)->dispatch(self::EVENT_KEY, $businessId, [
            'body_ar' => 'كتالوج المنصة فيه منتجات جاهزة لنشاطك — أضف سعرك وابدأ البيع من "منتجاتي".',
            'body_en' => 'The platform catalog now has products ready for your business — set your price in "My Products" and start selling.',
            'action_type' => 'open_business_products',
            'action_url' => '/business/products/create',
            'source_id' => $childId,
        ]);

        return true;
    }

    /**
     * Candidate business ids: retail actively linked to their child, never
     * listed anything, never nudged. Whether they actually have stock is
     * checked per-business in notifyIfEligible — cheap enough not to
     * pre-filter here, and it keeps this query index-friendly.
     *
     * @return Collection<int,int>
     */
    private function candidates(int $limit): Collection
    {
        $serviceId = $this->retailServiceId();

        if ($serviceId <= 0) {
            return collect();
        }

        $childIds = DB::table('category_platform_services')
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->pluck('child_id')
            ->unique();

        if ($childIds->isEmpty()) {
            return collect();
        }

        return DB::table('users')
            ->where('type', 'business')
            ->whereIn('category_child_id', $childIds)
            ->whereNotExists(fn ($q) => $q->selectRaw(1)
                ->from('business_catalog_listings')
                ->whereColumn('business_catalog_listings.business_id', 'users.id'))
            ->whereNotExists(fn ($q) => $q->selectRaw(1)
                ->from('app_notifications')
                ->whereColumn('app_notifications.user_id', 'users.id')
                ->where('app_notifications.source_type', self::EVENT_KEY))
            ->orderBy('users.id')
            ->limit($limit)
            ->pluck('id');
    }

    private function retailServiceId(): int
    {
        return (int) DB::table('platform_services')
            ->where('key', PlatformService::KEY_RETAIL)
            ->where('is_active', 1)
            ->value('id');
    }

    private function hasActiveLink(int $childId, int $serviceId): bool
    {
        return DB::table('category_platform_services')
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->exists();
    }

    private function alreadyNotified(int $businessId): bool
    {
        return DB::table('app_notifications')
            ->where('user_id', $businessId)
            ->where('source_type', self::EVENT_KEY)
            ->exists();
    }

    private function hasAnyListing(int $businessId): bool
    {
        return DB::table('business_catalog_listings')
            ->where('business_id', $businessId)
            ->exists();
    }

    /**
     * child_id alone, matching exactly what CatalogListingController::retailScope()
     * and ResolvesOwnerCatalog::allowedTypesByService() read — no root filter.
     *
     * @return array<int,string>
     */
    private function allowedTypeKeys(int $childId, int $serviceId): array
    {
        $restricted = DB::table('category_service_configs')
            ->where('child_id', $childId)
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->pluck('config')
            ->flatMap(function ($config) {
                $data = json_decode((string) $config, true) ?: [];

                return $data['allowed_item_types'] ?? [];
            })
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique()
            ->values();

        if ($restricted->isNotEmpty()) {
            return $restricted->all();
        }

        // Empty means "no restriction" everywhere else in this codebase (see
        // BoundUnboundedConfigsSeeder's docblock) — mirror it rather than
        // silently refusing to ever nudge an unbound child.
        return DB::table('platform_service_item_types')
            ->where('platform_service_id', $serviceId)
            ->where('is_active', 1)
            ->pluck('key')
            ->map(fn ($k) => (string) $k)
            ->all();
    }

    /** @param array<int,string> $typeKeys */
    private function hasStock(array $typeKeys): bool
    {
        $childIds = DB::table('product_category_children')
            ->whereIn('slug', $typeKeys)
            ->whereNull('deleted_at')
            ->pluck('id');

        if ($childIds->isEmpty()) {
            return false;
        }

        return DB::table('catalog_products')
            ->whereIn('product_category_child_id', $childIds)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->exists();
    }
}
