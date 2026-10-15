<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\TripParcel;
use App\Models\TripSchedule;
use App\Services\Schedules\TripParcelService;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * Parcels on a trip leg. The sender (and a receiver who has an account) use the customer routes; the driver's side is
 * business-gated at the route level. The delivery code is only ever shown to the sender and to an account receiver —
 * never to the carrier, who has to be told it by the receiver in person.
 */
final class TripParcelController extends Controller
{
    // ---- Customer side --------------------------------------------------

    /** POST schedules/{schedule}/parcels — the sender describes the parcel; the driver prices it next. */
    public function store(Request $request, int $schedule, TripParcelService $service)
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:2000'],
            // cash, recorded only: the sender at pickup or the receiver at delivery
            'payer' => ['nullable', 'in:sender,receiver'],
            'receiver_user_id' => ['nullable', 'integer'],
            'receiver_name' => ['nullable', 'string', 'max:150'],
            'receiver_phone' => ['nullable', 'string', 'max:40'],
        ]);

        $leg = TripSchedule::query()->findOrFail($schedule);
        $parcel = $service->request($request->user(), $leg, $data);

        return response()->json([
            'success' => true,
            'message' => __('أُرسل طلب الشحنة. سيحدد السائق سعرها.'),
            'data' => ['parcel' => $this->serialize($parcel, (int) $request->user()->id)],
        ], 201);
    }

    /** GET schedules/my-parcels — what I send and what is sent to me. */
    public function mine(Request $request)
    {
        $me = (int) $request->user()->id;

        $parcels = TripParcel::query()
            ->where(fn ($q) => $q->where('sender_id', $me)->orWhere('receiver_user_id', $me))
            ->with(['sender:id,name', 'receiver:id,name', 'business:id,name', 'schedule:id,origin_governorate_id,destination_governorate_id'])
            ->latest('id')
            ->paginate((int) $request->get('per_page', 20));

        $parcels->getCollection()->transform(fn (TripParcel $p) => $this->serialize($p, $me));

        return response()->json(['success' => true, 'data' => $parcels]);
    }

    public function accept(Request $request, int $parcel, TripParcelService $service)
    {
        $row = $this->senderParcelOrFail($request, $parcel);

        return $this->ok($service->accept($row), (int) $request->user()->id, __('قبلت سعر الشحنة.'));
    }

    public function code(Request $request, int $parcel, TripParcelService $service)
    {
        $row = $this->senderParcelOrFail($request, $parcel);

        return $this->ok($service->generateCode($row), (int) $request->user()->id, __('تم توليد كود التسليم. أعطِه للمستلم.'));
    }

    public function cancel(Request $request, int $parcel, TripParcelService $service)
    {
        $row = $this->senderParcelOrFail($request, $parcel);

        return $this->ok($service->cancel($row), (int) $request->user()->id, __('أُلغيت الشحنة.'));
    }

    // ---- Carrier side ---------------------------------------------------

    /** GET business/schedules/parcels */
    public function incoming(Request $request)
    {
        $status = trim((string) $request->get('status', ''));

        $query = TripParcel::query()
            ->where('business_id', BusinessContext::id($request))
            ->with(['sender:id,name', 'receiver:id,name', 'schedule:id,origin_governorate_id,destination_governorate_id'])
            ->latest('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        $parcels = $query->paginate((int) $request->get('per_page', 20));
        $parcels->getCollection()->transform(fn (TripParcel $p) => $this->serialize($p, null));

        return response()->json(['success' => true, 'data' => $parcels]);
    }

    public function price(Request $request, int $parcel, TripParcelService $service)
    {
        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0'],
            'seats' => ['nullable', 'integer', 'min:0', 'max:50'],
            // approximate: the actual hour is recorded when it is handed over
            'expected_arrival_at' => ['nullable', 'date'],
        ]);

        $row = $this->carrierParcelOrFail($request, $parcel);

        return $this->ok(
            $service->price($row, (float) $data['price'], (int) ($data['seats'] ?? 0), $data['expected_arrival_at'] ?? null),
            null,
            __('تم تسعير الشحنة.')
        );
    }

    /** POST business/schedules/parcels/{parcel}/load — {code, cash_collected?}: the sender's pickup QR, or its typed code. */
    public function load(Request $request, int $parcel, TripParcelService $service)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'cash_collected' => ['nullable', 'boolean'],
        ]);

        $row = $this->carrierParcelOrFail($request, $parcel);

        return $this->ok($service->load($row, $data['code'], (bool) ($data['cash_collected'] ?? false)), null, __('حُمّلت الشحنة على السيارة.'));
    }

    /** POST business/schedules/parcels/{parcel}/deliver — {code}: what the receiver tells the driver. */
    public function deliver(Request $request, int $parcel, TripParcelService $service)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'cash_collected' => ['nullable', 'boolean'],
        ]);

        $row = $this->carrierParcelOrFail($request, $parcel);

        return $this->ok(
            $service->deliver($row, $data['code'], (int) $request->user()->id, (bool) ($data['cash_collected'] ?? false)),
            null,
            __('سُلِّمت الشحنة.')
        );
    }

    public function reject(Request $request, int $parcel, TripParcelService $service)
    {
        $row = $this->carrierParcelOrFail($request, $parcel);

        return $this->ok($service->reject($row), null, __('رُفضت الشحنة.'));
    }

    // ---- helpers --------------------------------------------------------

    private function senderParcelOrFail(Request $request, int $id): TripParcel
    {
        return TripParcel::query()->where('id', $id)->where('sender_id', $request->user()->id)->firstOrFail();
    }

    private function carrierParcelOrFail(Request $request, int $id): TripParcel
    {
        return TripParcel::query()->where('id', $id)->where('business_id', BusinessContext::id($request))->firstOrFail();
    }

    private function ok(TripParcel $parcel, ?int $viewerId, string $message)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => ['parcel' => $this->serialize($parcel->loadMissing(['sender:id,name', 'receiver:id,name']), $viewerId)],
        ]);
    }

    /** $viewerId null = the carrier's view (never carries the delivery code). */
    private function serialize(TripParcel $p, ?int $viewerId): array
    {
        $mayReadCode = $viewerId !== null
            && ((int) $p->sender_id === $viewerId || ((int) $p->receiver_user_id > 0 && (int) $p->receiver_user_id === $viewerId));

        return [
            'id' => (int) $p->id,
            'trip_schedule_id' => (int) $p->trip_schedule_id,
            'business_id' => (int) $p->business_id,
            'business_name' => $p->relationLoaded('business') ? $p->business?->name : null,
            'sender' => ['id' => (int) $p->sender_id, 'name' => $p->relationLoaded('sender') ? $p->sender?->name : null],
            'receiver' => [
                'user_id' => $p->receiver_user_id ? (int) $p->receiver_user_id : null,
                'name' => $p->receiver_user_id && $p->relationLoaded('receiver') ? $p->receiver?->name : $p->receiver_name,
                'phone' => $p->receiver_user_id ? null : $p->receiver_phone,
            ],
            'description' => (string) $p->description,
            'price' => $p->price !== null ? (float) $p->price : null,
            'seats' => (int) $p->seats,
            'currency' => (string) $p->currency,
            'status' => (string) $p->status,
            'payer' => (string) $p->payer,
            'paid_at' => optional($p->paid_at)->toIso8601String(),
            'has_code' => (bool) $p->delivery_code_hash,
            // the sender's QR the driver scans to take the parcel — the sender only, until it is on the car
            'pickup_code' => $viewerId !== null && (int) $p->sender_id === $viewerId && $p->status === TripParcel::STATUS_ACCEPTED
                ? $p->pickup_code
                : null,
            'expected_arrival_at' => optional($p->expected_arrival_at)->toIso8601String(),
            'picked_up_at' => optional($p->picked_up_at)->toIso8601String(),
            'delivery_code' => $mayReadCode && $p->delivery_code && ! in_array($p->status, [TripParcel::STATUS_DELIVERED], true)
                ? (string) $p->delivery_code
                : null,
            'loaded_at' => optional($p->loaded_at)->toIso8601String(),
            'delivered_at' => optional($p->delivered_at)->toIso8601String(),
            'created_at' => optional($p->created_at)->toIso8601String(),
        ];
    }
}
