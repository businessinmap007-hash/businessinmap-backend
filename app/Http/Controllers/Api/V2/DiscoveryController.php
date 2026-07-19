<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\CategoryChild;
use App\Models\PlatformServiceItemType;
use App\Models\User;
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
        ]);

        $childId = (int) $data['child_id'];

        $services = DB::table('business_service_prices as p')
            ->join('platform_services as s', 's.id', '=', 'p.service_id')
            ->where('p.child_id', $childId)
            ->where('p.is_active', 1)
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
        ]);

        $childId = (int) $data['child_id'];

        $options = CategoryChild::query()->find($childId)?->activeOptions()->with('group')->get()
            ?? collect();

        $counts = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->where('u.type', 'business')
            ->where('u.category_child_id', $childId)
            ->whereIn('ou.option_id', $options->pluck('id'))
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
            ];
            $groups[$gid]['options'][] = [
                'id' => (int) $o->id,
                'name' => $this->label($o->name_ar, $o->name_en, ''),
                'businesses' => (int) ($counts[$o->id] ?? 0),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'child_id' => $childId,
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

        $offerExists($query);

        // A business must carry EVERY selected attribute — narrowing, not
        // widening, is what a filter is for.
        foreach ($optionIds as $optionId) {
            $query->whereExists(function ($sub) use ($optionId) {
                $sub->from('option_user')
                    ->whereColumn('option_user.user_id', 'users.id')
                    ->where('option_user.option_id', $optionId);
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

        $businesses->getCollection()->transform(function (User $b) use ($matched) {
            $arr = $b->only(['id', 'name', 'type', 'logo', 'category_id', 'category_child_id']);
            $arr['offered_types'] = $matched[$b->id] ?? [];

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

    private function label($ar, $en, $fallback): string
    {
        $ar = trim((string) $ar);
        $en = trim((string) $en);

        $primary   = app()->getLocale() === 'en' ? $en : $ar;
        $secondary = app()->getLocale() === 'en' ? $ar : $en;

        return $primary !== '' ? $primary : ($secondary !== '' ? $secondary : (string) $fallback);
    }
}
