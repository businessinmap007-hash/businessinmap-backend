<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\CategoryChild;
use App\Models\OptionGroup;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemType;
use App\Models\ServiceOptionGroupPlacement as Placement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * «مكونات الخدمة»: for each option group of a service, WHERE it shows (item
 * form / pricing / search filter / result card / …) and HOW it is used with an
 * item type of that service. category_child_option decides which child may use
 * a group; this decides what the group DOES inside the service.
 *
 * Scope: the service-wide default (child_id 0), or one child's override.
 */
class ServiceComponentsController extends Controller
{
    public function index(Request $request)
    {
        $services = PlatformService::query()->where('is_active', 1)->orderBy('name_ar')->get(['id', 'key', 'name_ar', 'name_en']);
        $serviceId = (int) $request->get('service_id', 0) ?: (int) optional($services->first())->id;
        $childId = (int) $request->get('child_id', 0);

        $itemTypes = PlatformServiceItemType::query()
            ->where('platform_service_id', $serviceId)->where('is_active', 1)
            ->orderBy('sort_order')->get(['key', 'name_ar', 'name_en']);

        $children = CategoryChild::query()
            ->whereIn('id', DB::table('category_platform_services')
                ->where('platform_service_id', $serviceId)->where('is_active', 1)->pluck('child_id'))
            ->orderBy('name_ar')->get(['id', 'name_ar']);

        $linkedGroupIds = DB::table('category_child_option as cco')
            ->join('options as o', 'o.id', '=', 'cco.option_id')
            ->join('category_platform_services as cps', function ($j) use ($serviceId) {
                $j->on('cps.child_id', '=', 'cco.child_id')
                    ->where('cps.platform_service_id', $serviceId)->where('cps.is_active', 1);
            })
            ->when($childId > 0, fn ($q) => $q->where('cco.child_id', $childId))
            ->distinct()->pluck('o.group_id');

        $rows = Placement::query()
            ->where('platform_service_id', $serviceId)->where('child_id', $childId)
            ->orderBy('sort_order')->orderBy('id')->get()->groupBy('option_group_id');

        $groups = OptionGroup::query()
            ->where('is_active', 1)
            ->whereIn('id', $linkedGroupIds->merge($rows->keys())->unique())
            ->inDisplayOrder()->get(['id', 'name_ar', 'name_en', 'reorder', 'price_role']);

        $defaults = $childId > 0
            ? Placement::query()->where('platform_service_id', $serviceId)->where('child_id', 0)->get()->groupBy('option_group_id')
            : collect();

        return view('admin-v2.service-components.index', [
            'services' => $services, 'serviceId' => $serviceId, 'childId' => $childId,
            'children' => $children, 'itemTypes' => $itemTypes, 'groups' => $groups,
            'rows' => $rows, 'defaults' => $defaults,
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => ['required', 'integer', 'exists:platform_services,id'],
            'child_id' => ['nullable', 'integer', 'min:0'],
            'rows' => ['nullable', 'array', 'max:500'],
            'rows.*.option_group_id' => ['required', 'integer', 'exists:option_groups,id'],
            'rows.*.item_type_key' => ['nullable', 'string', 'max:100'],
            'rows.*.surfaces' => ['nullable', 'array'],
            'rows.*.surfaces.*' => ['string', Rule::in(Placement::SURFACES)],
            'rows.*.usage' => ['nullable', Rule::in(Placement::USAGES)],
            'rows.*.input_type' => ['nullable', Rule::in(Placement::INPUT_TYPES)],
            'rows.*.is_required' => ['nullable', 'boolean'],
            'rows.*.is_active' => ['nullable', 'boolean'],
        ]);

        $serviceId = (int) $data['service_id'];
        $childId = (int) ($data['child_id'] ?? 0);
        $validTypes = PlatformServiceItemType::query()->where('platform_service_id', $serviceId)->pluck('key')->all();

        DB::transaction(function () use ($data, $serviceId, $childId, $validTypes) {
            $keep = [];

            foreach (array_values($data['rows'] ?? []) as $i => $row) {
                if (empty($row['usage'])) {
                    continue; // no usage chosen = «not configured», nothing to store
                }

                $type = (string) ($row['item_type_key'] ?? '');
                if ($type !== '' && ! in_array($type, $validTypes, true)) {
                    continue;
                }

                $placement = Placement::query()->updateOrCreate(
                    [
                        'platform_service_id' => $serviceId,
                        'option_group_id' => (int) $row['option_group_id'],
                        'child_id' => $childId,
                        'item_type_key' => $type,
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
                ->where('platform_service_id', $serviceId)->where('child_id', $childId)
                ->whereNotIn('id', $keep)->delete();
        });

        return redirect()
            ->route('admin.service-components.index', ['service_id' => $serviceId, 'child_id' => $childId])
            ->with('success', __('تم حفظ مكونات الخدمة.'));
    }
}
