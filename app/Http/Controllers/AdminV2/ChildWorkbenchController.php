<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\CategoryChildServiceFee;
use App\Services\Catalog\ChildServiceWriter;
use App\Services\CategoryChildOptionScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * One screen for one child: pick a root and a child, then see and edit BOTH
 * what describes it (options) and what it may sell (services) side by side.
 *
 * Until now those two axes lived on separate screens — /category-child-options
 * for the attributes and /categories/services-bulk for the services — so
 * answering "what is this child allowed to be and to do?" meant holding two
 * pages in your head. Every seeder written this session had to reason about the
 * pair together; the admin had no way to.
 *
 * Both panels are now keyed the same way: on the (root, child) PAIR. A shared
 * child answers a different question under a different root — a furniture
 * factory is asked about materials and output, a furniture showroom about
 * instalments and delivery — and until `category_child_option.category_id`
 * existed there was one option set for all four roots of «آثاث» at once.
 *
 * The one asymmetry left is deliberate and invisible here: a service config is
 * always written per root, while an option row may still be SHARED
 * (`category_id = 0`) and cover every root at once. That is what almost every
 * row is, and what a seeder writing a keyword rule means. A per-root row is
 * only ever created when a root actually diverges — see `saveOptions`.
 */
class ChildWorkbenchController extends Controller
{
    public function __construct(
        private readonly CategoryChildOptionScope $scope,
        private readonly ChildServiceWriter $writer
    )
    {
    }

    public function index(Request $request): View
    {
        $roots = DB::table('categories as c')
            ->whereExists(fn ($q) => $q->from('category_parent_child as pc')->whereColumn('pc.parent_id', 'c.id'))
            ->orderBy('c.id')
            ->get(['c.id', 'c.name_ar', 'c.slug']);

        $rootId = (int) $request->get('root_id', 0);
        $childId = (int) $request->get('child_id', 0);

        $children = $rootId
            ? DB::table('category_parent_child as pc')
                ->join('category_children_master as ch', 'ch.id', '=', 'pc.child_id')
                ->where('pc.parent_id', $rootId)
                ->orderBy('ch.name_ar')
                ->get(['ch.id', 'ch.name_ar'])
            : collect();

        // a child id left over from a previous root selection must not leak
        if ($childId && ! $children->contains('id', $childId)) {
            $childId = 0;
        }

        return view('admin-v2.child-workbench.index', [
            'roots' => $roots,
            'children' => $children,
            'rootId' => $rootId,
            'childId' => $childId,
            'child' => $childId ? $children->firstWhere('id', $childId) : null,
            'optionPanel' => $childId ? $this->optionPanel($rootId, $childId) : null,
            'servicePanel' => $childId ? $this->servicePanel($rootId, $childId) : null,
            'feePanel' => $childId ? $this->feePanel($rootId, $childId) : null,
            'sharedRoots' => $childId ? $this->sharedRoots($rootId, $childId) : collect(),
        ]);
    }

    /**
     * Save the option set for THIS root only.
     *
     * The interesting case is withdrawing an option that is currently shared
     * across every root. Deleting the shared row would silently strip it from
     * the other roots too — the very bug this screen exists to end. So the row
     * is SPLIT instead: the shared row goes, and the option is re-granted as an
     * explicit row under each of the child's other roots. Nothing is
     * materialised until a root actually disagrees, so the table stays as small
     * as the disagreements are.
     */
    public function saveOptions(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'root_id' => ['required', 'integer', 'min:1'],
            'child_id' => ['required', 'integer', 'exists:category_children_master,id'],
            'option_ids' => ['nullable', 'array'],
            'option_ids.*' => ['integer', 'exists:options,id'],
        ]);

        $rootId = (int) $data['root_id'];
        $childId = (int) $data['child_id'];
        $wanted = collect($data['option_ids'] ?? [])->map(fn ($id) => (int) $id)->unique();

        $existing = $this->scope->idsFor($childId, $rootId);

        // A merchant's own answer outranks the catalogue: refuse to withdraw an
        // option a business under THIS root and child has already ticked. Scoped
        // to the root, so a showroom's answer no longer pins a factory's list.
        $chosen = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->where('u.category_child_id', $childId)
            ->where('u.category_id', $rootId)
            ->whereIn('ou.option_id', $existing->diff($wanted))
            ->pluck('ou.option_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        $result = $this->scope->syncFor($childId, $rootId, $wanted->all(), $chosen->all());

        $message = __('تم حفظ الخيارات لهذا القسم الرئيسي وحده.');

        if ($result['split'] > 0) {
            $message .= ' ' . __(':count خيارًا كانت مشتركة، فأُبقيت كما هي تحت الأقسام الأخرى.', ['count' => $result['split']]);
        }

        if ($chosen->isNotEmpty()) {
            $message .= ' ' . __('أُبقي :count خيارًا لأن تاجرًا اختارها بالفعل.', ['count' => $chosen->count()]);
        }

        // route(..., false) for the relative URL; redirect()->route()'s third
        // argument is the HTTP STATUS, not the absolute flag.
        return redirect()
            ->to(route('admin.child-workbench.index', ['root_id' => $rootId, 'child_id' => $childId], false))
            ->with('status', $message);
    }

    public function saveServices(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'root_id' => ['required', 'integer', 'exists:categories,id'],
            'child_id' => ['required', 'integer', 'exists:category_children_master,id'],
            'services' => ['nullable', 'array'],
        ]);

        $rootId = (int) $data['root_id'];
        $childId = (int) $data['child_id'];

        $services = DB::table('platform_services')->where('is_active', 1)->get(['id', 'key']);

        DB::transaction(function () use ($services, $request, $rootId, $childId) {
            foreach ($services as $service) {
                $input = (array) $request->input("services.{$service->id}", []);

                $types = collect($input['item_types'] ?? [])->map(fn ($k) => (string) $k)->unique()->values();

                $valid = DB::table('platform_service_item_types')
                    ->where('platform_service_id', $service->id)
                    ->whereIn('key', $types)
                    ->pluck('key');

                $enabled = ! empty($input['enabled']);

                $patch = [
                    'allowed_item_types' => $valid->values()->all(),
                    'item_groups' => $this->groupsOf((int) $service->id, $valid),
                ] + $this->bookingFlags($service->key, $input);

                // One call writes the link and the config together. They used
                // to be three statements here, and a config without an active
                // link is a service the merchant can never reach.
                if ($enabled) {
                    $this->writer->enable(
                        rootId: $rootId,
                        childId: $childId,
                        serviceId: (int) $service->id,
                        configPatch: $patch,
                        source: 'child_workbench'
                    );

                    continue;
                }

                // Turning it off still records what was chosen, so re-enabling
                // it next week finds the item types where the admin left them.
                $this->writer->enable(
                    rootId: $rootId,
                    childId: $childId,
                    serviceId: (int) $service->id,
                    configPatch: $patch,
                    source: 'child_workbench'
                );

                $this->writer->disable($rootId, $childId, (int) $service->id);
            }
        });

        return redirect()
            ->to(route('admin.child-workbench.index', ['root_id' => $rootId, 'child_id' => $childId], false))
            ->with('status', __('تم حفظ الخدمات.'));
    }


    /**
     * The booking flag decides whether the merchant must register reservable
     * units. It is read from the form so an admin can flip it, but it is only
     * written for booking — nothing else uses it.
     */
    private function bookingFlags(string $serviceKey, array $input): array
    {
        if ($serviceKey !== 'booking') {
            return [];
        }

        return ['requires_bookable_item' => ! empty($input['requires_bookable_item'])];
    }

    /** Item groups are derived from the chosen types, never entered by hand. */
    private function groupsOf(int $serviceId, $typeKeys): array
    {
        if ($typeKeys->isEmpty()) {
            return [];
        }

        return DB::table('platform_service_item_group_type as gt')
            ->join('platform_service_item_types as t', 't.id', '=', 'gt.item_type_id')
            ->join('platform_service_item_groups as g', 'g.id', '=', 'gt.group_id')
            ->where('g.platform_service_id', $serviceId)
            ->whereIn('t.key', $typeKeys)
            ->distinct()
            ->pluck('g.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }


    /**
     * Selected options first, then every other option folded into its group —
     * all of it read for THIS root: the child's shared rows plus its own.
     *
     * @return array{selected:\Illuminate\Support\Collection,groups:\Illuminate\Support\Collection,locked:\Illuminate\Support\Collection,shared:\Illuminate\Support\Collection}
     */
    private function optionPanel(int $rootId, int $childId): array
    {
        $rows = DB::table('category_child_option')
            ->where('child_id', $childId)
            ->whereIn('category_id', [0, $rootId])
            ->get(['option_id', 'category_id']);

        $selectedIds = $rows->pluck('option_id')->unique();
        $shared = $rows->where('category_id', 0)->pluck('option_id')->unique();

        $all = DB::table('options as o')
            ->leftJoin('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where(fn ($q) => $q->whereNull('g.id')->orWhere('g.is_active', 1))
            ->orderBy('g.reorder')
            ->orderBy('o.id')
            ->get(['o.id', 'o.name_ar', 'o.group_id', 'g.name_ar as group_name']);

        // options a merchant under this root already ticked cannot be withdrawn
        $locked = DB::table('option_user as ou')
            ->join('users as u', 'u.id', '=', 'ou.user_id')
            ->where('u.category_child_id', $childId)
            ->where('u.category_id', $rootId)
            ->pluck('ou.option_id')
            ->unique();

        [$selected, $rest] = $all->partition(fn ($o) => $selectedIds->contains($o->id));

        return [
            'selected' => $selected->groupBy(fn ($o) => $o->group_name ?: __('بلا مجموعة')),
            'groups' => $rest->groupBy(fn ($o) => $o->group_name ?: __('بلا مجموعة')),
            'locked' => $locked,
            'shared' => $shared,
        ];
    }

    /**
     * The same shape for services: what this child may already sell, then the
     * rest of each service's catalogue folded away.
     */
    /**
     * Save the fees for this (root, child), one row per service.
     *
     * A fee is only written for a service this child is actually offered —
     * charging for something it cannot sell is a row nothing will ever read,
     * and it is exactly the kind of orphan that made these tables hard to
     * trust. The service panel above decides that; this follows it.
     */
    public function saveFees(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'root_id' => ['required', 'integer', 'min:1'],
            'child_id' => ['required', 'integer', 'exists:category_children_master,id'],
            'fees' => ['nullable', 'array'],
        ]);

        $rootId = (int) $data['root_id'];
        $childId = (int) $data['child_id'];

        $offered = DB::table('category_platform_services')
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->where('is_active', 1)
            ->pluck('platform_service_id')
            ->map(fn ($id) => (int) $id);

        $written = 0;
        $skipped = [];

        DB::transaction(function () use ($request, $rootId, $childId, $offered, &$written, &$skipped) {
            foreach (DB::table('platform_services')->where('is_active', 1)->pluck('name_ar', 'id') as $serviceId => $serviceName) {
                $serviceId = (int) $serviceId;
                $input = (array) $request->input("fees.{$serviceId}", []);

                if (! $offered->contains($serviceId)) {
                    if (! empty($input['is_active'])) {
                        $skipped[] = $serviceName;
                    }

                    continue;
                }

                CategoryChildServiceFee::query()->updateOrCreate(
                    [
                        'category_id' => $rootId,
                        'child_id' => $childId,
                        'platform_service_id' => $serviceId,
                    ],
                    [
                        'is_active' => ! empty($input['is_active']) ? 1 : 0,
                        'business_fee_enabled' => ! empty($input['business_fee_enabled']) ? 1 : 0,
                        'business_fee_type' => $this->feeType($input['business_fee_type'] ?? null),
                        'business_fee_amount' => max(0, (float) ($input['business_fee_amount'] ?? 0)),
                        'client_fee_enabled' => ! empty($input['client_fee_enabled']) ? 1 : 0,
                        'client_fee_type' => $this->feeType($input['client_fee_type'] ?? null),
                        'client_fee_amount' => max(0, (float) ($input['client_fee_amount'] ?? 0)),
                        'currency' => strtoupper(trim((string) ($input['currency'] ?? ''))) ?: CategoryChildServiceFee::DEFAULT_CURRENCY,
                        'updated_at' => now(),
                    ]
                );

                $written++;
            }
        });

        $message = __('تم حفظ الرسوم لـ :count خدمة.', ['count' => $written]);

        if ($skipped !== []) {
            $message .= ' ' . __('تُجوهلت (غير مفعّلة لهذا القسم): :names', ['names' => implode('، ', $skipped)]);
        }

        return redirect()
            ->to(route('admin.child-workbench.index', ['root_id' => $rootId, 'child_id' => $childId], false))
            ->with('status', $message);
    }

    private function feeType($value): string
    {
        $type = strtolower(trim((string) $value));

        return in_array($type, CategoryChildServiceFee::CALC_TYPES, true)
            ? $type
            : CategoryChildServiceFee::CALC_TYPE_FIXED;
    }

    /**
     * What the platform charges on this (root, child, service).
     *
     * The third thing that is decided per child and used to live on its own
     * screen — /admin/category-child-service-fees — so «what may this child
     * sell» and «what does it cost to sell it» were two pages that never
     * mentioned each other. Same key, same screen now. The bulk fee screens
     * stay: they answer a different question, «all of these at once».
     */
    private function feePanel(int $rootId, int $childId)
    {
        $fees = CategoryChildServiceFee::query()
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->get()
            ->keyBy('platform_service_id');

        return DB::table('platform_services')
            ->where('is_active', 1)
            ->orderBy('id')
            ->get(['id', 'key', 'name_ar'])
            ->map(function ($service) use ($fees) {
                $fee = $fees->get($service->id);

                return (object) [
                    'id' => (int) $service->id,
                    'name' => $service->name_ar,
                    'exists' => (bool) $fee,
                    'is_active' => (bool) ($fee->is_active ?? false),
                    'business_enabled' => (bool) ($fee->business_fee_enabled ?? false),
                    'business_type' => (string) ($fee->business_fee_type ?? CategoryChildServiceFee::CALC_TYPE_FIXED),
                    'business_amount' => (float) ($fee->business_fee_amount ?? 0),
                    'client_enabled' => (bool) ($fee->client_fee_enabled ?? false),
                    'client_type' => (string) ($fee->client_fee_type ?? CategoryChildServiceFee::CALC_TYPE_FIXED),
                    'client_amount' => (float) ($fee->client_fee_amount ?? 0),
                    'currency' => (string) ($fee->currency ?? CategoryChildServiceFee::DEFAULT_CURRENCY),
                ];
            });
    }

    private function servicePanel(int $rootId, int $childId)
    {
        $configs = DB::table('category_service_configs')
            ->where('category_id', $rootId)
            ->where('child_id', $childId)
            ->get(['platform_service_id', 'config', 'is_active'])
            ->keyBy('platform_service_id');

        return DB::table('platform_services')
            ->where('is_active', 1)
            ->orderBy('id')
            ->get(['id', 'key', 'name_ar'])
            ->map(function ($service) use ($configs) {
                $row = $configs->get($service->id);
                $config = $row ? (json_decode($row->config ?: '{}', true) ?: []) : [];
                $allowed = collect($config['allowed_item_types'] ?? []);

                $types = DB::table('platform_service_item_types as t')
                    ->leftJoin('platform_service_item_group_type as gt', 'gt.item_type_id', '=', 't.id')
                    ->leftJoin('platform_service_item_groups as g', 'g.id', '=', 'gt.group_id')
                    ->where('t.platform_service_id', $service->id)
                    ->where('t.is_active', 1)
                    ->orderBy('g.sort_order')
                    ->orderBy('t.sort_order')
                    ->get(['t.key', 't.name_ar', 'g.name_ar as group_name'])
                    ->unique('key');

                [$selected, $rest] = $types->partition(fn ($t) => $allowed->contains($t->key));

                return (object) [
                    'id' => (int) $service->id,
                    'key' => $service->key,
                    'name' => $service->name_ar,
                    'enabled' => (bool) ($row->is_active ?? false),
                    'exists' => (bool) $row,
                    'requiresBookable' => (bool) ($config['requires_bookable_item'] ?? false),
                    'selected' => $selected->groupBy(fn ($t) => $t->group_name ?: __('بلا مجموعة')),
                    'groups' => $rest->groupBy(fn ($t) => $t->group_name ?: __('بلا مجموعة')),
                ];
            });
    }

    /** The other roots this child sits under — an option edit reaches them too. */
    private function sharedRoots(int $rootId, int $childId)
    {
        return DB::table('category_parent_child as pc')
            ->join('categories as r', 'r.id', '=', 'pc.parent_id')
            ->where('pc.child_id', $childId)
            ->where('pc.parent_id', '!=', $rootId)
            ->pluck('r.name_ar');
    }
}
