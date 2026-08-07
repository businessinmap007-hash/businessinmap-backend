<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CategoryChild;
use App\Models\PlatformServiceItemType;
use App\Models\User;
use App\Services\OfferingDiscovery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customer discovery on the "offer = filter = index" principle: a business's
 * priced item types (business_service_prices) are what it offers AND what the
 * customer filters by. Powers the journey:
 *   search a specialty (category child) → pick a service + item types →
 *   see the businesses that actually offer them → book.
 */
final class DiscoveryController extends Controller
{
    /**
     * Filters available inside a category child: the services its businesses
     * offer, and (for the chosen service) the item types they offer, grouped by
     * branch — with a business count each. Only meaningful (non-empty) filters.
     */
    public function filters(Request $request)
    {
        $data = $request->validate([
            'child_id' => ['required', 'integer', 'min:1'],
            'service_id' => ['nullable', 'integer', 'min:1'],
            'open_now' => ['nullable', 'boolean'],
        ]);

        $childId = (int) $data['child_id'];
        $openNow = $request->boolean('open_now');
        $hours = app(\App\Services\BusinessHoursService::class);

        $services = DB::table('business_service_prices as p')
            ->join('platform_services as s', 's.id', '=', 'p.service_id')
            ->where('p.child_id', $childId)
            ->where('p.is_active', 1)
            ->when($openNow, fn ($q) => $hours->applyOpenNow($q, 'p.business_id'))
            ->groupBy('s.id', 's.key', 's.name_ar', 's.name_en')
            ->selectRaw('s.id, s.key, s.name_ar, s.name_en, COUNT(DISTINCT p.business_id) AS businesses')
            ->orderByDesc('businesses')
            ->get()
            ->map(fn ($s) => [
                'id' => (int) $s->id,
                'key' => (string) $s->key,
                'name' => $this->label($s->name_ar, $s->name_en, $s->key),
                'businesses' => (int) $s->businesses,
            ])->values();

        $serviceId = (int) ($data['service_id'] ?? 0);
        if ($serviceId <= 0) {
            $serviceId = (int) ($services->first()['id'] ?? 0);
        }

        $offered = DB::table('business_service_prices')
            ->where('child_id', $childId)
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->when($openNow, fn ($q) => $hours->applyOpenNow($q, 'business_service_prices.business_id'))
            ->groupBy('bookable_item_type')
            ->selectRaw('bookable_item_type AS type_key, COUNT(DISTINCT business_id) AS businesses')
            ->get()
            ->keyBy('type_key');

        $branches = [];
        $ungrouped = [];

        if ($offered->isNotEmpty()) {
            $types = PlatformServiceItemType::query()
                ->where('platform_service_id', $serviceId)
                ->whereIn('key', $offered->keys()->all())
                ->with('groups:id,name_ar,name_en')
                ->get(['id', 'key', 'name_ar', 'name_en']);

            $branchMap = [];
            foreach ($types as $t) {
                $entry = [
                    'key' => (string) $t->key,
                    'name' => $this->label($t->name_ar, $t->name_en, $t->key),
                    'businesses' => (int) ($offered[$t->key]->businesses ?? 0),
                ];

                if ($t->groups->isEmpty()) {
                    $ungrouped[] = $entry;
                    continue;
                }

                foreach ($t->groups as $g) {
                    $branchMap[$g->id] ??= [
                        'id' => (int) $g->id,
                        'name' => $this->label($g->name_ar, $g->name_en, ''),
                        'types' => [],
                    ];
                    $branchMap[$g->id]['types'][] = $entry;
                }
            }

            $branches = array_values($branchMap);

            // item type keys offered but with no matching platform_service_item_type row
            $known = $types->pluck('key')->flip();
            foreach ($offered as $key => $row) {
                if (! $known->has($key)) {
                    $ungrouped[] = ['key' => (string) $key, 'name' => (string) $key, 'businesses' => (int) $row->businesses];
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'child_id' => $childId,
                'service_id' => $serviceId,
                'services' => $services,
                'branches' => $branches,
                'ungrouped_types' => $ungrouped,
            ],
        ]);
    }

    /**
     * The attributes axis for a category child: option groups + options that
     * were actually linked to it (category_child_option), each with how many
     * businesses in that child currently carry it (option_user). A business-
     * level property like «تقسيط» — never something a merchant prices alone.
     */
    public function attributes(Request $request)
    {
        $data = $request->validate([
            'child_id' => ['required', 'integer', 'min:1'],
            'category_id' => ['nullable', 'integer', 'min:1'],
            'open_now' => ['nullable', 'boolean'],
        ]);

        $childId = (int) $data['child_id'];

        // A shared child answers a different question under each root, so the
        // browsing root narrows the filter list. Without one the answer is the
        // union over every root — right for a search that spans them.
        $rootId = (int) ($data['category_id'] ?? 0);

        $options = CategoryChild::query()->find($childId)?->activeOptionsForParent($rootId)->with('group')->get()
            ?? collect();

        $counts = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->where('u.type', 'business')
            ->where('u.category_child_id', $childId)
            ->when($rootId > 0, fn ($q) => $q->where('u.category_id', $rootId))
            ->whereIn('ou.option_id', $options->pluck('id'))
            ->when(
                $request->boolean('open_now'),
                fn ($q) => app(\App\Services\BusinessHoursService::class)->applyOpenNow($q, 'u.id')
            )
            ->groupBy('ou.option_id')
            ->selectRaw('ou.option_id, COUNT(DISTINCT ou.user_id) AS businesses')
            ->pluck('businesses', 'option_id');

        $groups = [];
        foreach ($options as $o) {
            $gid = (int) ($o->group_id ?? 0);
            $groups[$gid] ??= [
                'id' => $gid ?: null,
                'name' => $o->group ? $this->label($o->group->name_ar, $o->group->name_en, '') : '',
                'options' => [],
                // Sort keys, dropped before the response is sent.
                '_rank' => $o->group ? $o->group->roleRank() : 99,
                '_reorder' => (int) ($o->group->reorder ?? 999999),
            ];
            $groups[$gid]['options'][] = [
                'id' => (int) $o->id,
                'name' => $this->label($o->name_ar, $o->name_en, ''),
                'businesses' => (int) ($counts[$o->id] ?? 0),
            ];
        }

        // What is bought, then what changes its price, then what only describes
        // it. Ordered by `reorder` alone the list opened on «واي فاي مجاني» and
        // buried «غرفة مزدوجة» — a facility above the thing being paid for.
        uksort($groups, function ($a, $b) use ($groups) {
            return [$groups[$a]['_rank'], $groups[$a]['_reorder'], $a]
                <=> [$groups[$b]['_rank'], $groups[$b]['_reorder'], $b];
        });

        $groups = array_map(function (array $group) {
            unset($group['_rank'], $group['_reorder']);

            return $group;
        }, $groups);

        return response()->json([
            'success' => true,
            'data' => [
                'child_id' => $childId,
                'category_id' => $rootId ?: null,
                'groups' => array_values($groups),
            ],
        ]);
    }

    /**
     * Businesses in a category child that offer the chosen service / item types
     * and (optionally) carry every selected attribute. Each result carries the
     * matched item types it actually offers.
     */
    public function businesses(Request $request)
    {
        $data = $request->validate([
            'child_id' => ['required', 'integer', 'min:1'],
            'service_id' => ['nullable', 'integer', 'min:1'],
            'item_types' => ['nullable', 'array'],
            'item_types.*' => ['string', 'max:100'],
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:120'],
            'open_now' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $childId = (int) $data['child_id'];
        $serviceId = (int) ($data['service_id'] ?? 0);
        $itemTypes = array_values(array_filter((array) ($data['item_types'] ?? []), fn ($t) => trim((string) $t) !== ''));
        $optionIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($data['option_ids'] ?? [])),
            fn ($id) => $id > 0
        )));
        $q = trim((string) ($data['q'] ?? ''));

        $offerExists = function (Builder $query) use ($serviceId, $itemTypes) {
            $query->whereExists(function ($sub) use ($serviceId, $itemTypes) {
                $sub->from('business_service_prices as p')
                    ->whereColumn('p.business_id', 'users.id')
                    ->where('p.is_active', 1);

                if ($serviceId > 0) {
                    $sub->where('p.service_id', $serviceId);
                }
                if ($itemTypes) {
                    $sub->whereIn('p.bookable_item_type', $itemTypes);
                }
            });
        };

        $query = User::query()
            ->where('type', 'business')
            ->where('category_child_id', $childId)
            ->when($q !== '', fn (Builder $w) => $w->where(fn (Builder $x) => $x
                ->where('name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")));

        /*
         * A business appears for its child whether or not it has priced
         * anything. Requiring a priced row unconditionally hid 1,702 of 1,704
         * accounts: the taxonomy and the pricing screen are both built, but no
         * merchant has used them yet, and a customer reading an empty list
         * cannot tell an empty platform from a broken one.
         *
         * The requirement stays exactly where it earns its keep — when the
         * customer NAMES what he wants. Asking for «سحب عينة بالمنزل» must not
         * return a doctor who never said he does it; browsing the clinics
         * should return every clinic.
         */
        if ($itemTypes) {
            $offerExists($query);
        }

        // Skip shops that are closed right now, when asked. A shop with no
        // hours configured is treated as available and is not hidden.
        if ($request->boolean('open_now')) {
            app(\App\Services\BusinessHoursService::class)->filterOpenNow($query);
        }

        // A business must carry EVERY selected attribute — narrowing, not
        // widening, is what a filter is for. Two things count as carrying it:
        // the business said so about itself (option_user), OR it has a priced
        // offering that sells it. A hospital that priced «كشف عظام» does عظام
        // whether or not it also ticked the box.
        foreach ($optionIds as $optionId) {
            $query->where(function (Builder $w) use ($optionId) {
                $w->whereExists(function ($sub) use ($optionId) {
                    $sub->from('option_user')
                        ->whereColumn('option_user.user_id', 'users.id')
                        ->where('option_user.option_id', $optionId);
                })->orWhereExists(function ($sub) use ($optionId) {
                    $sub->from('offering_options as oo')
                        ->leftJoin('business_service_prices as p', function ($join) {
                            $join->on('p.id', '=', 'oo.offering_id')
                                ->where('oo.offering_type', '=', \App\Models\BusinessServicePrice::class);
                        })
                        ->leftJoin('menu_items as m', function ($join) {
                            $join->on('m.id', '=', 'oo.offering_id')
                                ->where('oo.offering_type', '=', \App\Models\MenuItem::class);
                        })
                        ->where('oo.option_id', $optionId)
                        ->whereRaw('COALESCE(p.is_active, m.is_active, 0) = 1')
                        ->whereRaw('COALESCE(p.business_id, m.business_id) = users.id');
                });
            });
        }

        $businesses = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($data['per_page'] ?? 20), ['id', 'name', 'type', 'logo', 'category_id', 'category_child_id'])
            ->withQueryString();

        $matched = $this->matchedTypes(
            $businesses->getCollection()->pluck('id')->all(),
            $serviceId,
            $itemTypes
        );

        $openNow = app(\App\Services\BusinessHoursService::class)
            ->openNowMap($businesses->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all());

        $businesses->getCollection()->transform(function (User $b) use ($matched, $openNow) {
            $arr = $b->only(['id', 'name', 'type', 'logo', 'category_id', 'category_child_id']);
            $arr['offered_types'] = $matched[$b->id] ?? [];
            // So the card can say «اتصل للسعر» rather than showing nothing and
            // leaving the customer to guess why a business has no prices.
            $arr['has_prices'] = ($matched[$b->id] ?? []) !== [];
            $arr['is_open_now'] = $openNow[(int) $b->id] ?? true;

            return $arr;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'query' => [
                    'child_id' => $childId,
                    'service_id' => $serviceId ?: null,
                    'item_types' => $itemTypes,
                    'option_ids' => $optionIds,
                    'q' => $q ?: null,
                ],
                'businesses' => $businesses,
            ],
        ]);
    }

    /**
     * The priced OFFERINGS behind a filter, not just the businesses holding
     * them: «كشف عظام — 300 — مستشفى BIM».
     *
     * Filtering on «عظام» used to answer with a list of hospitals and stop.
     * The customer opened one and met a price list that never mentioned عظام
     * again, because a price row could not say what it sold. It can now.
     */
    public function offerings(Request $request, OfferingDiscovery $offerings)
    {
        $data = $request->validate([
            'child_id' => ['required', 'integer', 'min:1'],
            'service_id' => ['nullable', 'integer', 'min:1'],
            'item_types' => ['nullable', 'array'],
            'item_types.*' => ['string', 'max:100'],
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $optionIds = $this->cleanIds($data['option_ids'] ?? []);
        $itemTypes = array_values(array_filter((array) ($data['item_types'] ?? []), fn ($t) => trim((string) $t) !== ''));

        $results = $offerings->search(
            (int) $data['child_id'],
            $optionIds,
            (int) ($data['service_id'] ?? 0),
            $itemTypes,
            (int) ($data['per_page'] ?? 20)
        );

        $results->setCollection($results->getCollection()->map(fn ($row) => $this->offeringPayload($row)));

        return response()->json([
            'success' => true,
            'data' => [
                'query' => [
                    'child_id' => (int) $data['child_id'],
                    'service_id' => (int) ($data['service_id'] ?? 0) ?: null,
                    'item_types' => $itemTypes,
                    'option_ids' => $optionIds,
                ],
                'offerings' => $results,
            ],
        ]);
    }

    /**
     * GET /api/v2/discovery/offering-lines — the «بنود» a child actually sells,
     * each one a whole combination the customer can tap in ONE step:
     *
     *     غرفة نوم — مودرن   12
     *     غرفة نوم — كلاسيك   8
     *
     * The road used to be محافظة → تصنيف → ابن → خيارات → خدمات: the customer
     * assembled the combination out of loose options and then narrowed a second
     * time by service, for one question. Each row here carries the exact
     * `option_ids` to hand straight back to /discovery/offerings.
     */
    public function offeringLines(Request $request, OfferingDiscovery $offerings)
    {
        $data = $request->validate([
            'child_id' => ['required', 'integer', 'min:1'],
            'service_id' => ['nullable', 'integer', 'min:1'],
            'item_types' => ['nullable', 'array'],
            'item_types.*' => ['string', 'max:100'],
        ]);

        $itemTypes = array_values(array_filter((array) ($data['item_types'] ?? []), fn ($t) => trim((string) $t) !== ''));

        return response()->json([
            'success' => true,
            'data' => [
                'query' => [
                    'child_id' => (int) $data['child_id'],
                    'service_id' => (int) ($data['service_id'] ?? 0) ?: null,
                    'item_types' => $itemTypes,
                ],
                'lines' => $offerings->combinations(
                    (int) $data['child_id'],
                    (int) ($data['service_id'] ?? 0),
                    $itemTypes
                )->values(),
            ],
        ]);
    }

    private function offeringPayload($row): array
    {
        $name = fn ($o) => $o ? $this->label($o->name_ar, $o->name_en, '') : null;

        $parts = collect([$row->line])->merge($row->modifiers)->filter()
            ->map($name)->filter()->values();

        return [
            'id' => (int) $row->offering_id,
            'source' => $row->source,
            'label' => $parts->implode(' — '),
            'line' => $row->line ? ['id' => (int) $row->line->id, 'name' => $name($row->line)] : null,
            'modifiers' => $row->modifiers->map(fn ($m) => ['id' => (int) $m->id, 'name' => $name($m)])->values(),
            'price' => (float) $row->price,
            'currency' => $row->currency ?: 'EGP',
            'service_id' => $row->service_id ? (int) $row->service_id : null,
            'item_type' => $row->bookable_item_type,
            'image' => $row->image,
            'own_name' => $this->label($row->menu_name_ar, $row->menu_name_en, ''),
            'business' => [
                'id' => (int) $row->business_id,
                'name' => (string) $row->business_name,
                'logo' => $row->business_logo,
            ],
        ];
    }

    /** @return array<int,int> */
    private function cleanIds($ids): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', (array) $ids),
            fn ($id) => $id > 0
        )));
    }

    /**
     * [business_id => [{key,name}]] of the item types each business offers
     * within the current service/item-type filter.
     */
    private function matchedTypes(array $businessIds, int $serviceId, array $itemTypes): array
    {
        if (! $businessIds) {
            return [];
        }

        $rows = DB::table('business_service_prices')
            ->whereIn('business_id', $businessIds)
            ->where('is_active', 1)
            ->when($serviceId > 0, fn ($q) => $q->where('service_id', $serviceId))
            ->when($itemTypes, fn ($q) => $q->whereIn('bookable_item_type', $itemTypes))
            ->get(['business_id', 'service_id', 'bookable_item_type']);

        $names = PlatformServiceItemType::query()
            ->when($serviceId > 0, fn ($q) => $q->where('platform_service_id', $serviceId))
            ->whereIn('key', $rows->pluck('bookable_item_type')->unique()->all())
            ->get(['key', 'name_ar', 'name_en'])
            ->mapWithKeys(fn ($t) => [$t->key => $this->label($t->name_ar, $t->name_en, $t->key)]);

        $out = [];
        foreach ($rows as $r) {
            $key = (string) $r->bookable_item_type;
            $out[(int) $r->business_id][] = ['key' => $key, 'name' => $names[$key] ?? $key];
        }

        return $out;
    }

    /**
     * The services hub for a specialty (category child): every platform service
     * CONFIGURED as available for that child (category_platform_services), in
     * display order, with a count of businesses that price each. This is the
     * app's "services tab" — stable tabs even for a service no one prices yet,
     * unlike `filters` which only lists services already offered.
     */
    public function services(Request $request)
    {
        $data = $request->validate([
            'child_id' => ['required', 'integer', 'min:1'],
            'open_now' => ['nullable', 'boolean'],
        ]);

        $childId = (int) $data['child_id'];
        $openNow = $request->boolean('open_now');
        $hours = app(\App\Services\BusinessHoursService::class);

        // What services this child offers (the availability catalog).
        $available = DB::table('category_platform_services as cps')
            ->join('platform_services as s', 's.id', '=', 'cps.platform_service_id')
            ->where('cps.child_id', $childId)
            ->where('cps.is_active', 1)
            ->where('s.is_active', 1)
            ->orderBy('cps.sort_order')
            ->orderBy('s.id')
            ->get(['s.id', 's.key', 's.name_ar', 's.name_en', 's.supports_deposit', 'cps.sort_order']);

        // Businesses pricing each service for this child (for a tab badge).
        $counts = DB::table('business_service_prices as p')
            ->where('p.child_id', $childId)
            ->where('p.is_active', 1)
            ->when($openNow, fn ($q) => $hours->applyOpenNow($q, 'p.business_id'))
            ->groupBy('p.service_id')
            ->selectRaw('p.service_id, COUNT(DISTINCT p.business_id) AS businesses')
            ->pluck('businesses', 'p.service_id');

        $services = $available->map(fn ($s) => [
            'id' => (int) $s->id,
            'key' => (string) $s->key,
            'name' => $this->label($s->name_ar, $s->name_en, $s->key),
            'supports_deposit' => (bool) $s->supports_deposit,
            'businesses' => (int) ($counts[$s->id] ?? 0),
        ])->values();

        return response()->json([
            'success' => true,
            'data' => [
                'child_id' => $childId,
                'services' => $services,
            ],
        ]);
    }

    private function label($ar, $en, $fallback): string
    {
        $ar = trim((string) $ar);
        $en = trim((string) $en);

        $primary   = app()->getLocale() === 'en' ? $en : $ar;
        $secondary = app()->getLocale() === 'en' ? $ar : $en;

        return $primary !== '' ? $primary : ($secondary !== '' ? $secondary : (string) $fallback);
    }
}
