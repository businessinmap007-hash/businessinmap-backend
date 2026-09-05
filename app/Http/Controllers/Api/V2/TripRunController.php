<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\TripRun;
use App\Models\TripRunManifestItem;
use App\Models\TripSchedule;
use App\Services\Schedules\TripRunService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Live execution of a trip leg — the driver's start/arrive/advance/reconcile
 * actions and the business's live-progress view. Business-gated at the route
 * level (business.member:schedules), same as everything else in this
 * service; see App\Services\Schedules\TripRunService for the state machine.
 */
final class TripRunController extends Controller
{
    public function start(Request $request, int $schedule, TripRunService $service)
    {
        $leg = TripSchedule::query()
            ->where('id', $schedule)
            ->where('business_id', BusinessContext::id($request))
            ->firstOrFail();

        $data = $request->validate([
            'passenger_count' => ['nullable', 'integer', 'min:1'],
            'manifest' => ['nullable', 'array'],
            'manifest.*.label' => ['required_with:manifest', 'string', 'max:150'],
            'manifest.*.unit' => ['nullable', 'string', 'max:24'],
            'manifest.*.assigned_qty' => ['required_with:manifest', 'integer', 'min:1'],
        ]);

        $run = $service->start(
            schedule: $leg,
            actor: $request->user(),
            passengerCount: $data['passenger_count'] ?? null,
            manifestLines: $data['manifest'] ?? []
        );

        return response()->json([
            'success' => true,
            'message' => __('بدأت الرحلة.'),
            'data' => ['run' => $this->serialize($run)],
        ], 201);
    }

    public function index(Request $request)
    {
        $status = trim((string) $request->get('status', ''));

        $query = TripRun::query()
            ->where('business_id', BusinessContext::id($request))
            ->with(['stops', 'manifestItems', 'schedule:id,mode,vehicle_label'])
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $runs = $query->paginate((int) $request->get('per_page', 20));
        $runs->getCollection()->transform(fn (TripRun $r) => $this->serialize($r));

        return response()->json(['success' => true, 'data' => $runs]);
    }

    public function show(Request $request, int $run)
    {
        return response()->json([
            'success' => true,
            'data' => ['run' => $this->serialize($this->ownedRun($request, $run))],
        ]);
    }

    public function arrive(Request $request, int $run, TripRunService $service)
    {
        $result = $service->markArrived($this->ownedRun($request, $run));

        return response()->json([
            'success' => true,
            'message' => __('تم تسجيل الوصول.'),
            'data' => ['run' => $this->serialize($result)],
        ]);
    }

    public function advance(Request $request, int $run, TripRunService $service)
    {
        $result = $service->advance($this->ownedRun($request, $run));

        return response()->json([
            'success' => true,
            'message' => __('تم تحديث حالة الرحلة.'),
            'data' => ['run' => $this->serialize($result)],
        ]);
    }

    public function reconcile(Request $request, int $run, TripRunService $service)
    {
        $row = $this->ownedRun($request, $run);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.manifest_item_id' => ['required', 'integer'],
            'items.*.delivered_qty' => ['required', 'integer', 'min:0'],
            'items.*.returned_qty' => ['required', 'integer', 'min:0'],
        ]);

        $ids = TripRunManifestItem::query()->where('trip_run_id', $row->id)->pluck('id')->all();

        $items = [];
        foreach ($data['items'] as $line) {
            $id = (int) $line['manifest_item_id'];

            if (! in_array($id, $ids, true)) {
                throw ValidationException::withMessages(['items' => __('صنف غير موجود في قائمة هذه الرحلة.')]);
            }

            $items[$id] = ['delivered_qty' => $line['delivered_qty'], 'returned_qty' => $line['returned_qty']];
        }

        $result = $service->reconcile($row, $items);

        return response()->json([
            'success' => true,
            'message' => __('تم إنجاز المهمة بالكامل.'),
            'data' => ['run' => $this->serialize($result)],
        ]);
    }

    private function ownedRun(Request $request, int $runId): TripRun
    {
        return TripRun::query()
            ->where('id', $runId)
            ->where('business_id', BusinessContext::id($request))
            ->with(['stops', 'manifestItems', 'schedule:id,mode,vehicle_label'])
            ->firstOrFail();
    }

    private function serialize(TripRun $r): array
    {
        return [
            'id' => (int) $r->id,
            'trip_schedule_id' => (int) $r->trip_schedule_id,
            'mode' => $r->relationLoaded('schedule') && $r->schedule ? (string) $r->schedule->mode : null,
            'vehicle_label' => $r->relationLoaded('schedule') ? optional($r->schedule)->vehicle_label : null,
            'status' => (string) $r->status,
            'passenger_count' => $r->passenger_count !== null ? (int) $r->passenger_count : null,
            'started_at' => optional($r->started_at)->toIso8601String(),
            'completed_at' => optional($r->completed_at)->toIso8601String(),
            'current_stop_id' => optional($r->currentStop())->id,
            'stops' => $r->stops->map(fn ($s) => [
                'id' => (int) $s->id,
                'sequence' => (int) $s->sequence,
                'label' => $s->label,
                'address' => $s->address,
                'status' => (string) $s->status,
                'arrived_at' => optional($s->arrived_at)->toIso8601String(),
                'completed_at' => optional($s->completed_at)->toIso8601String(),
            ])->values(),
            'manifest_items' => $r->manifestItems->map(fn ($m) => [
                'id' => (int) $m->id,
                'label' => $m->label,
                'unit' => $m->unit,
                'assigned_qty' => (int) $m->assigned_qty,
                'delivered_qty' => $m->delivered_qty !== null ? (int) $m->delivered_qty : null,
                'returned_qty' => $m->returned_qty !== null ? (int) $m->returned_qty : null,
                'remaining_qty' => $r->status === TripRun::STATUS_COMPLETED ? $m->remainingQty() : null,
            ])->values(),
        ];
    }
}
