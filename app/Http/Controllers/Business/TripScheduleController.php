<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Country;
use App\Models\Governorate;
use App\Models\PlatformService;
use App\Models\PlatformServiceItemType;
use App\Models\TripSchedule;
use App\Services\Schedules\TripReservationService;
use App\Services\Schedules\TripScheduleValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "My trip legs" for the carrier: publish the route they run on a given day,
 * with which vehicle, capacity and price. The web face of the carrier API —
 * both share TripScheduleValidator, so the domain rules live in one place.
 * Every query is scoped to the logged-in owner.
 */
class TripScheduleController extends Controller
{
    public function __construct(
        private readonly TripScheduleValidator $validator,
        private readonly TripReservationService $reservations
    ) {}

    private function businessId(): int
    {
        return (int) Auth::id();
    }

    private function scopedLeg(int $id): TripSchedule
    {
        return TripSchedule::query()
            ->where('business_id', $this->businessId())
            ->findOrFail($id);
    }

    public function index(Request $request): View
    {
        $mode = trim((string) $request->get('mode', ''));
        $status = trim((string) $request->get('status', ''));

        $rows = TripSchedule::query()
            ->where('business_id', $this->businessId())
            ->with([
                'vehicleType:id,key,name_ar,name_en',
                'originGovernorate:id,name_ar',
                'destinationGovernorate:id,name_ar',
                'originCountry:id,name_ar',
                'destinationCountry:id,name_ar',
            ])
            ->when($mode !== '', fn ($query) => $query->where('mode', $mode))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        // What is actually still sellable on each leg, in one bulk query.
        $remaining = $this->reservations->remainingCapacityFor(
            $rows->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all()
        );

        return view('business.schedules.index', [
            'rows' => $rows,
            'remaining' => $remaining,
            'mode' => $mode,
            'status' => $status,
        ]);
    }

    public function create(): View
    {
        return view('business.schedules.create', $this->formData(
            new TripSchedule([
                'scope' => TripSchedule::SCOPE_DOMESTIC,
                'schedule_pattern' => TripSchedule::PATTERN_WEEKLY,
                'status' => TripSchedule::STATUS_ACTIVE,
                'currency' => 'EGP',
            ])
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validator->validated($request, $this->businessId());
        $data['business_id'] = $this->businessId();

        TripSchedule::create($data);

        return redirect()
            ->route('business.schedules.index')
            ->with('success', 'تم نشر خط التشغيل بنجاح.');
    }

    public function edit(int $id): View
    {
        return view('business.schedules.edit', $this->formData($this->scopedLeg($id)));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $row = $this->scopedLeg($id);

        $row->update($this->validator->validated($request, $this->businessId(), $row));

        return redirect()
            ->route('business.schedules.index')
            ->with('success', 'تم تحديث خط التشغيل.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $this->scopedLeg($id)->delete();

        return redirect()
            ->route('business.schedules.index')
            ->with('success', 'تم حذف خط التشغيل.');
    }

    /**
     * Everything the publish form needs. The pickers are preloaded rather than
     * fetched: it keeps the form free of AJAX, and the lists are small enough
     * (27 governorates, ~1.3k cities, 73 countries) to ship inline.
     */
    private function formData(TripSchedule $row): array
    {
        return [
            'row' => $row,
            'modes' => TripSchedule::modeLabels(),
            'patterns' => TripSchedule::patternLabels(),
            'scopeLabels' => TripSchedule::scopeLabels(),
            'statuses' => TripSchedule::statusLabels(),
            'days' => TripSchedule::dayLabels(),
            'vehicleTypesByMode' => $this->vehicleTypesByMode(),
            'governorates' => Governorate::query()->orderBy('name_ar')->get(['id', 'name_ar']),
            'countries' => Country::query()->orderBy('name_ar')->get(['id', 'name_ar']),
            'citiesByGovernorate' => $this->citiesByGovernorate(),
            'parentLegs' => $this->parentLegOptions($row),
        ];
    }

    /**
     * The platform's vehicle/cargo classes for the scheduling service, keyed by
     * mode so the form can narrow the picker once a mode is chosen.
     *
     * @return array<string, array<int, array{id:int, label:string, unit:?string}>>
     */
    private function vehicleTypesByMode(): array
    {
        $serviceId = (int) PlatformService::query()
            ->where('key', PlatformService::KEY_SCHEDULES)
            ->value('id');

        $map = array_fill_keys(TripSchedule::modes(), []);

        if ($serviceId <= 0) {
            return $map;
        }

        PlatformServiceItemType::query()
            ->forService($serviceId)
            ->active()
            ->ordered()
            ->get()
            ->each(function (PlatformServiceItemType $type) use (&$map) {
                $mode = (string) data_get($type->meta, 'mode');

                if (! array_key_exists($mode, $map)) {
                    return;
                }

                $map[$mode][] = [
                    'id' => (int) $type->id,
                    'label' => $type->displayName('ar'),
                    'unit' => data_get($type->meta, 'default_unit'),
                ];
            });

        return $map;
    }

    /**
     * @return array<string, array<int, array{id:int, label:string}>>
     */
    private function citiesByGovernorate(): array
    {
        return City::query()
            ->orderBy('name_ar')
            ->get(['id', 'governorate_id', 'name_ar'])
            ->groupBy('governorate_id')
            ->map(fn ($cities) => $cities
                ->map(fn (City $c) => ['id' => (int) $c->id, 'label' => (string) $c->name_ar])
                ->values()
                ->all())
            ->all();
    }

    /**
     * The owner's other legs, offered as the parent of a backhaul return leg.
     * Excludes the leg being edited (a leg cannot return to itself) and other
     * return legs (a backhaul does not chain off a backhaul).
     */
    private function parentLegOptions(TripSchedule $row): \Illuminate\Support\Collection
    {
        return TripSchedule::query()
            ->where('business_id', $this->businessId())
            ->where('is_return_leg', false)
            ->when($row->exists, fn ($query) => $query->whereKeyNot($row->id))
            ->with(['originGovernorate:id,name_ar', 'destinationGovernorate:id,name_ar'])
            ->orderByDesc('id')
            ->limit(100)
            ->get();
    }
}
