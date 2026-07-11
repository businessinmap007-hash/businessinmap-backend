<?php

namespace Database\Seeders;

use App\Models\CategoryPlatformService;
use App\Models\PlatformService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Business-offers enablement — the offers analogue of the branch pattern.
 *
 * business_offers has NO item types by design: a commercial offer is a
 * polymorphic wrapper (offerable_type/offerable_id) around another service's
 * item, so its taxonomy IS the typed services' taxonomies. The only per-child
 * knob is the on/off link, and the approved rule is derivational:
 *
 *   a child may publish offers  ⟺  it has at least one ACTIVE typed service
 *   (booking / menu / delivery) link in the same root context.
 *
 * Enables the offers link where the rule holds, deactivates it where it
 * doesn't (nothing underneath to offer). Dynamic — no data file; re-running
 * after future services-bulk changes re-derives the correct set. Idempotent.
 */
class BusinessOffersEnablementSeeder extends Seeder
{
    public function run(): void
    {
        $offers = PlatformService::where('key', PlatformService::KEY_BUSINESS_OFFERS)->first();

        if (! $offers) {
            return;
        }

        $offersId = (int) $offers->id;

        $typedIds = PlatformService::query()
            ->whereIn('key', [
                PlatformService::KEY_BOOKING,
                PlatformService::KEY_MENU,
                PlatformService::KEY_DELIVERY,
            ])
            ->pluck('id')
            ->all();

        // Every (root, child) pair with at least one active typed-service link.
        // Legacy root-level rows (child_id NULL/0, from the pre-child model)
        // are excluded — offers enablement is a child-level concern.
        $eligible = DB::table('category_platform_services')
            ->whereIn('platform_service_id', $typedIds)
            ->where('is_active', 1)
            ->whereNotNull('child_id')
            ->where('child_id', '>', 0)
            ->select('category_id', 'child_id')
            ->distinct()
            ->get();

        $eligibleKeys = $eligible
            ->map(fn ($r) => ((int) $r->category_id) . ':' . ((int) $r->child_id))
            ->flip();

        $enabled = 0;

        foreach ($eligible as $pair) {
            $link = CategoryPlatformService::query()->firstOrNew([
                'category_id' => (int) $pair->category_id,
                'child_id' => (int) $pair->child_id,
                'platform_service_id' => $offersId,
            ]);

            if (! $link->exists || ! $link->is_active) {
                $sort = (int) ($link->sort_order ?: 0);

                if ($sort <= 0) {
                    $sort = 1 + (int) CategoryPlatformService::query()
                        ->where('category_id', (int) $pair->category_id)
                        ->where('child_id', (int) $pair->child_id)
                        ->max('sort_order');
                }

                $link->fill(['is_active' => 1, 'sort_order' => $sort])->save();
                $enabled++;
            }
        }

        // Deactivate offers links whose (root, child) has no active typed service.
        $stale = CategoryPlatformService::query()
            ->where('platform_service_id', $offersId)
            ->where('is_active', 1)
            ->get(['id', 'category_id', 'child_id'])
            ->filter(fn ($l) => ! isset($eligibleKeys[((int) $l->category_id) . ':' . ((int) $l->child_id)]));

        if ($stale->isNotEmpty()) {
            CategoryPlatformService::query()
                ->whereIn('id', $stale->pluck('id')->all())
                ->update(['is_active' => 0, 'updated_at' => now()]);
        }

        $this->command?->info(
            'business_offers enablement: eligible=' . $eligible->count()
            . ' newly_enabled=' . $enabled
            . ' deactivated_stale=' . $stale->count()
        );
    }
}
