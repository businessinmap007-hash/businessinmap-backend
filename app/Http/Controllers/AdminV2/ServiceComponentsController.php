<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\ServiceOptionGroupPlacement as Placement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * «مكونات الخدمة»: pick a root and a child, see the services that child
 * offers, then for ONE service at a time say what each of the child's option
 * groups is there (the priced line, a price modifier, or the description) and
 * where it shows. Settings are for that child only.
 *
 * The «item» here is the option group. category_child_option decides which
 * groups the child carries; this decides what each one DOES in the service.
 */
class ServiceComponentsController extends Controller
{
    /** What a group most likely is, taken from its price_role until the admin says otherwise. */
    private const ROLE_DEFAULTS = [
        OptionGroup::ROLE_LINE => [Placement::USAGE_DEFINES_ITEM, [Placement::SURFACE_ITEM_FORM, Placement::SURFACE_PRICING, Placement::SURFACE_RESULT_CARD]],
        OptionGroup::ROLE_MODIFIER => [Placement::USAGE_CHANGES_PRICE, [Placement::SURFACE_ITEM_FORM, Placement::SURFACE_PRICING]],
        OptionGroup::ROLE_DESCRIPTIVE => [Placement::USAGE_DESCRIPTIVE, [Placement::SURFACE_ITEM_FORM, Placement::SURFACE_ITEM_DETAIL]],
    ];

    public function index(Request $request)
    {
        $roots = Category::query()
            ->where('parent_id', 0)
            ->with(['children' => fn ($q) => $q->select('category_children_master.id', 'name_ar', 'name_en')->orderBy('name_ar')])
            ->orderBy('name_ar')->get(['id', 'name_ar', 'name_en'])
            ->filter(fn ($root) => $root->children->isNotEmpty())->values();

        $rootId = (int) $request->get('root_id', 0);
        $root = $roots->firstWhere('id', $rootId) ?: $roots->first();
        $rootId = (int) optional($root)->id;

        $children = $root?->children ?? collect();
        $childId = (int) $request->get('child_id', 0);
        $child = $children->firstWhere('id', $childId) ?: $children->first();
        $childId = (int) optional($child)->id;

        $services = $this->servicesFor($rootId, $childId);
        $serviceId = (int) $request->get('service_id', 0);
        $service = $services->firstWhere('id', $serviceId) ?: $services->first();
        $serviceId = (int) optional($service)->id;

        $groups = $this->groupsFor($rootId, $childId);
        $saved = $serviceId > 0
            ? Placement::query()->where('platform_service_id', $serviceId)->where('child_id', $childId)
                ->where('item_type_key', '')->get()->keyBy('option_group_id')
            : collect();

        return view('admin-v2.service-components.index', [
            'roots' => $roots, 'rootId' => $rootId, 'children' => $children, 'childId' => $childId,
            'services' => $services, 'serviceId' => $serviceId, 'groups' => $groups, 'saved' => $saved,
            'roleDefaults' => self::ROLE_DEFAULTS,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'root_id' => ['required', 'integer', 'exists:categories,id'],
            'child_id' => ['required', 'integer', 'exists:category_children_master,id'],
            'service_id' => ['required', 'integer', 'exists:platform_services,id'],
            'next' => ['nullable', 'boolean'],
            'rows' => ['nullable', 'array', 'max:300'],
            'rows.*.option_group_id' => ['required', 'integer', 'exists:option_groups,id'],
            'rows.*.surfaces' => ['nullable', 'array'],
            'rows.*.surfaces.*' => ['string', Rule::in(Placement::SURFACES)],
            'rows.*.usage' => ['nullable', Rule::in(Placement::USAGES)],
            'rows.*.input_type' => ['nullable', Rule::in(Placement::INPUT_TYPES)],
            'rows.*.is_required' => ['nullable', 'boolean'],
            'rows.*.is_active' => ['nullable', 'boolean'],
        ]);

        $rootId = (int) $data['root_id'];
        $childId = (int) $data['child_id'];
        $serviceId = (int) $data['service_id'];

        abort_unless($this->servicesFor($rootId, $childId)->contains('id', $serviceId), 422, __('هذه الخدمة غير متاحة لهذا الابن.'));

        $allowedGroupIds = $this->groupsFor($rootId, $childId)->pluck('id')->all();

        DB::transaction(function () use ($data, $serviceId, $childId, $allowedGroupIds) {
            $keep = [];

            foreach (array_values($data['rows'] ?? []) as $i => $row) {
                if (empty($row['usage']) || ! in_array((int) $row['option_group_id'], $allowedGroupIds, true)) {
                    continue; // no role chosen, or not a group this child carries
                }

                $placement = Placement::query()->updateOrCreate(
                    [
                        'platform_service_id' => $serviceId,
                        'option_group_id' => (int) $row['option_group_id'],
                        'child_id' => $childId,
                        'item_type_key' => '',
                    ],
                    [
                        'surfaces' => array_values(array_unique($row['surfaces'] ?? [])),
                        'usage' => $row['usage'],
                        'input_type' => $row['input_type'] ?? Placement::INPUT_SINGLE,
                        'is_required' => (bool) ($row['is_required'] ?? false),
                        'is_active' => (bool) ($row['is_active'] ?? true),
                        'sort_order' => ($i + 1) * 10,
                    ]
                );

                $keep[] = $placement->id;
            }

            Placement::query()
                ->where('platform_service_id', $serviceId)->where('child_id', $childId)->where('item_type_key', '')
                ->whereNotIn('id', $keep)->delete();
        });

        $target = $serviceId;
        $message = __('تم حفظ إعدادات الخدمة لهذا الابن.');

        if ($request->boolean('next')) {
            $ids = $this->servicesFor($rootId, $childId)->pluck('id')->values();
            $pos = $ids->search($serviceId);
            $following = $pos === false ? null : $ids->get($pos + 1);

            if ($following) {
                $target = (int) $following;
            } else {
                $message = __('تم الحفظ — هذه آخر خدمة لهذا الابن.');
            }
        }

        return redirect()
            ->route('admin.service-components.index', ['root_id' => $rootId, 'child_id' => $childId, 'service_id' => $target])
            ->with('success', $message);
    }

    /** Services this child offers under this root. */
    private function servicesFor(int $rootId, int $childId)
    {
        if ($rootId <= 0 || $childId <= 0) {
            return collect();
        }

        $ids = DB::table('category_platform_services')
            ->where('category_id', $rootId)->where('child_id', $childId)->where('is_active', 1)
            ->pluck('platform_service_id');

        return PlatformService::query()->whereIn('id', $ids)->where('is_active', 1)
            ->orderBy('name_ar')->get(['id', 'key', 'name_ar', 'name_en']);
    }

    /** The option groups this child carries under this root (its «items»). */
    private function groupsFor(int $rootId, int $childId)
    {
        if ($childId <= 0) {
            return collect();
        }

        $groupIds = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->where('cco.child_id', $childId)
            ->whereIn('cco.category_id', [0, $rootId])
            ->distinct()->pluck('o.group_id');

        return OptionGroup::query()->where('is_active', 1)->whereIn('id', $groupIds)
            ->inDisplayOrder()->get(['id', 'name_ar', 'name_en', 'reorder', 'price_role']);
    }
}
