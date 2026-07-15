<?php

namespace App\Http\Controllers\AdminV2;

use App\Http\Controllers\Controller;
use App\Models\TripReservation;
use App\Models\TripSchedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Read-only AdminV2 oversight for the scheduling/routes service: published trip
 * legs and the reservations placed against them.
 */
final class TripScheduleAdminController extends Controller
{
    private const PER_PAGE_ALLOWED = [10, 20, 50, 100];

    public function schedules(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $mode = trim((string) $request->get('mode', ''));
        $scope = trim((string) $request->get('scope', ''));
        $status = trim((string) $request->get('status', ''));
        $perPage = $this->perPage($request);

        $query = TripSchedule::query()
            ->with(['business:id,name', 'originGovernorate:id,name_ar', 'destinationGovernorate:id,name_ar', 'originCountry:id,name_ar', 'destinationCountry:id,name_ar', 'vehicleType:id,name_ar,name_en'])
            ->withCount(['reservations as active_reservations_count' => fn ($r) => $r->holdingCapacity()]);

        if ($q !== '') {
            $query->whereHas('business', fn (Builder $b) => $b->where('name', 'like', "%{$q}%"));
        }
        if (in_array($mode, TripSchedule::modes(), true)) {
            $query->where('mode', $mode);
        }
        if (in_array($scope, TripSchedule::scopes(), true)) {
            $query->where('scope', $scope);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $schedules = $query->latest('id')->paginate($perPage)->withQueryString();

        $totals = [
            'count' => TripSchedule::query()->count(),
            'active' => TripSchedule::query()->where('status', TripSchedule::STATUS_ACTIVE)->count(),
            'international' => TripSchedule::query()->where('scope', TripSchedule::SCOPE_INTERNATIONAL)->count(),
            'reservations' => TripReservation::query()->count(),
        ];

        return view('admin-v2.trip-schedules.index', compact('schedules', 'q', 'mode', 'scope', 'status', 'perPage', 'totals'));
    }

    public function reservations(Request $request)
    {
        $status = trim((string) $request->get('status', ''));
        $source = trim((string) $request->get('source', ''));
        $perPage = $this->perPage($request);

        $query = TripReservation::query()
            ->with(['business:id,name', 'client:id,name', 'schedule:id,mode,origin_governorate_id,destination_governorate_id']);

        if ($status !== '') {
            $query->where('status', $status);
        }
        if (in_array($source, [TripReservation::SOURCE_APP, TripReservation::SOURCE_OFFLINE], true)) {
            $query->where('source', $source);
        }

        $reservations = $query->latest('id')->paginate($perPage)->withQueryString();

        return view('admin-v2.trip-schedules.reservations', compact('reservations', 'status', 'source', 'perPage'));
    }

    private function perPage(Request $request): int
    {
        $perPage = (int) $request->get('per_page', 20);

        return in_array($perPage, self::PER_PAGE_ALLOWED, true) ? $perPage : 20;
    }
}
