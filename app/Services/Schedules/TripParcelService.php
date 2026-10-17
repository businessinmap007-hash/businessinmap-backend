<?php

namespace App\Services\Schedules;

use App\Models\TripParcel;
use App\Models\TripRun;
use App\Models\TripSchedule;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use App\Support\HandoverCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Parcels riding with the car. The driver prices, the sender accepts, and BOTH handovers are proved with the platform's
 * handover code core (a QR that encodes the code, or the code typed): the driver takes the parcel from the sender with
 * the PICKUP code and gives it to the receiver with the DELIVERY code — so he can neither claim a parcel he never
 * received nor one he never handed over. The price is paid in cash and only recorded (the wallet is for fees,
 * deposits and guarantees): by the sender at pickup or by the receiver at delivery.
 */
final class TripParcelService
{
    public function __construct(
        private readonly TripReservationService $reservations,
        private readonly NotificationDispatcherService $notifications,
    ) {}

    /**
     * @param  array{description:string, payer?:?string, receiver_user_id?:?int, receiver_name?:?string, receiver_phone?:?string}  $data
     */
    public function request(User $sender, TripSchedule $schedule, array $data): TripParcel
    {
        if ((string) $schedule->status !== TripSchedule::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['schedule' => __('خط التشغيل غير متاح.')]);
        }

        if ((int) $schedule->business_id === (int) $sender->id) {
            throw ValidationException::withMessages(['schedule' => __('لا يمكنك إرسال طرد على خط تملكه.')]);
        }

        $receiverId = isset($data['receiver_user_id']) ? (int) $data['receiver_user_id'] : null;

        if ($receiverId !== null && ($receiverId === (int) $sender->id || ! User::query()->whereKey($receiverId)->exists())) {
            throw ValidationException::withMessages(['receiver_user_id' => __('المستلم غير صالح.')]);
        }

        // no account: the receiver is a name and a phone
        if ($receiverId === null && (trim((string) ($data['receiver_name'] ?? '')) === '' || trim((string) ($data['receiver_phone'] ?? '')) === '')) {
            throw ValidationException::withMessages(['receiver_name' => __('اكتب اسم المستلم ورقم هاتفه.')]);
        }

        $payer = (string) ($data['payer'] ?? TripParcel::PAYER_RECEIVER);

        if (! in_array($payer, TripParcel::PAYERS, true)) {
            throw ValidationException::withMessages(['payer' => __('اختر من يدفع: المرسل عند التسليم للسائق أو المستلم عند الاستلام.')]);
        }

        $parcel = TripParcel::create([
            'trip_schedule_id' => (int) $schedule->id,
            'business_id' => (int) $schedule->business_id,
            'sender_id' => (int) $sender->id,
            'receiver_user_id' => $receiverId,
            'receiver_name' => $receiverId === null ? trim((string) $data['receiver_name']) : null,
            'receiver_phone' => $receiverId === null ? trim((string) $data['receiver_phone']) : null,
            'description' => trim((string) $data['description']),
            'payer' => $payer,
            'currency' => (string) ($schedule->currency ?: 'EGP'),
            'status' => TripParcel::STATUS_REQUESTED,
        ]);

        $this->notify('trip_parcel_requested', (int) $parcel->business_id, (int) $sender->id, $parcel, 'open_carrier_parcel');

        return $parcel;
    }

    /**
     * The driver sets the price, how many seats it takes (0 when it rides in the boot) and — approximately — when it
     * will arrive. The actual hour is recorded at delivery.
     */
    public function price(TripParcel $parcel, float $price, int $seats = 0, ?string $expectedArrivalAt = null): TripParcel
    {
        $this->assertStatus($parcel, [TripParcel::STATUS_REQUESTED, TripParcel::STATUS_PRICED]);

        if ($price < 0) {
            throw ValidationException::withMessages(['price' => __('سعر الشحنة غير صالح.')]);
        }

        $parcel->update([
            'price' => round($price, 2),
            'seats' => max(0, $seats),
            'expected_arrival_at' => $expectedArrivalAt ? \Illuminate\Support\Carbon::parse($expectedArrivalAt) : $parcel->expected_arrival_at,
            'status' => TripParcel::STATUS_PRICED,
        ]);

        $this->notify('trip_parcel_priced', (int) $parcel->sender_id, (int) $parcel->business_id, $parcel, 'open_trip_parcel');

        return $parcel->refresh();
    }

    /** The sender takes the driver's price; a parcel that takes seats claims them now. Its pickup code is born here. */
    public function accept(TripParcel $parcel): TripParcel
    {
        $this->assertStatus($parcel, [TripParcel::STATUS_PRICED]);

        DB::transaction(function () use ($parcel) {
            /** @var TripSchedule $leg */
            $leg = TripSchedule::query()->whereKey($parcel->trip_schedule_id)->lockForUpdate()->firstOrFail();

            if ((int) $parcel->seats > 0 && $leg->capacity !== null) {
                if ($this->reservations->heldUnits((int) $leg->id) + (int) $parcel->seats > (int) $leg->capacity) {
                    throw ValidationException::withMessages(['seats' => __('لا تتسع الرحلة لمقاعد هذه الشحنة.')]);
                }
            }

            $pickup = HandoverCode::generate();

            $parcel->update([
                'status' => TripParcel::STATUS_ACCEPTED,
                'pickup_code' => $pickup,
                'pickup_code_hash' => HandoverCode::hash($pickup),
                'pickup_attempts' => 0,
            ]);
        });

        // a parcel that takes the last seats fills the trip: a no-show's held deposit goes back
        $this->reservations->releaseNoShowDepositsIfFull((int) $parcel->trip_schedule_id);

        $this->notify('trip_parcel_accepted', (int) $parcel->business_id, (int) $parcel->sender_id, $parcel, 'open_carrier_parcel');

        return $parcel->refresh();
    }

    /**
     * The sender's «كود جديد»: replaces the ONE code he holds — the pickup code while the parcel waits for the driver,
     * the delivery code once it is on the car (and it lifts the lock after wrong tries at delivery).
     */
    public function regenerateCode(TripParcel $parcel): TripParcel
    {
        $this->assertStatus($parcel, [TripParcel::STATUS_ACCEPTED, TripParcel::STATUS_LOADED]);

        $code = HandoverCode::generate();

        if ($parcel->status === TripParcel::STATUS_ACCEPTED) {
            $parcel->update(['pickup_code' => $code, 'pickup_code_hash' => HandoverCode::hash($code), 'pickup_attempts' => 0]);
        } else {
            $parcel->update(['delivery_code' => $code, 'delivery_code_hash' => HandoverCode::hash($code), 'code_attempts' => 0]);
        }

        return $parcel->refresh();
    }

    /**
     * Taken from the sender: the driver scans the sender's QR (or types the code). That code is spent here and a NEW one
     * is born for the delivery — the sender's screen shows it from now on, so the driver (who saw the first) can never
     * claim the handover without the receiver. Cash is recorded here when the SENDER pays.
     */
    public function load(TripParcel $parcel, string $code, bool $cashCollected = false): TripParcel
    {
        $this->assertStatus($parcel, [TripParcel::STATUS_ACCEPTED]);

        $this->assertCode($parcel, 'pickup_attempts', (string) $parcel->pickup_code_hash, $code);

        $delivery = HandoverCode::generate();

        $parcel->update([
            'pickup_code' => null,
            'delivery_code' => $delivery,
            'delivery_code_hash' => HandoverCode::hash($delivery),
            'code_attempts' => 0,
            'status' => TripParcel::STATUS_LOADED,
            'loaded_at' => now(),
            'picked_up_at' => now(),
            'paid_at' => $cashCollected && $parcel->payer === TripParcel::PAYER_SENDER ? now() : $parcel->paid_at,
        ]);

        $this->notify('trip_parcel_picked_up', (int) $parcel->sender_id, (int) $parcel->business_id, $parcel, 'open_trip_parcel');

        return $parcel->refresh();
    }

    /** Handed over: the driver scans the receiver's QR or types the code the receiver told him. */
    public function deliver(TripParcel $parcel, string $code, int $driverId, bool $cashCollected = false): TripParcel
    {
        $this->assertStatus($parcel, [TripParcel::STATUS_LOADED]);

        $this->assertCode($parcel, 'code_attempts', (string) $parcel->delivery_code_hash, $code);

        $parcel->update([
            'status' => TripParcel::STATUS_DELIVERED,
            'delivered_at' => now(),
            'delivered_by' => $driverId,
            'paid_at' => $cashCollected && $parcel->payer === TripParcel::PAYER_RECEIVER ? now() : $parcel->paid_at,
        ]);

        $this->notify('trip_parcel_delivered', (int) $parcel->sender_id, (int) $parcel->business_id, $parcel, 'open_trip_parcel');

        if ($parcel->receiver_user_id) {
            $this->notify('trip_parcel_delivered', (int) $parcel->receiver_user_id, (int) $parcel->business_id, $parcel, 'open_trip_parcel');
        }

        return $parcel->refresh();
    }

    public function reject(TripParcel $parcel): TripParcel
    {
        $this->assertStatus($parcel, [TripParcel::STATUS_REQUESTED, TripParcel::STATUS_PRICED]);
        $parcel->update(['status' => TripParcel::STATUS_REJECTED]);
        $this->notify('trip_parcel_rejected', (int) $parcel->sender_id, (int) $parcel->business_id, $parcel, 'open_trip_parcel');

        return $parcel->refresh();
    }

    public function cancel(TripParcel $parcel): TripParcel
    {
        $this->assertStatus($parcel, [TripParcel::STATUS_REQUESTED, TripParcel::STATUS_PRICED, TripParcel::STATUS_ACCEPTED]);
        $parcel->update(['status' => TripParcel::STATUS_CANCELLED]);

        return $parcel->refresh();
    }

    // ------------------------------------------------------------------ run

    /** A run starts: the leg's accepted / loaded parcels ride in it, like its passengers. */
    public function attachToRun(TripRun $run): void
    {
        TripParcel::query()
            ->where('trip_schedule_id', $run->trip_schedule_id)
            ->whereNull('trip_run_id')
            ->whereIn('status', [TripParcel::STATUS_ACCEPTED, TripParcel::STATUS_LOADED])
            ->update(['trip_run_id' => (int) $run->id]);
    }

    /**
     * The run is over: what was not handed over is settled — a parcel still on the car is `returned`, one never taken
     * from its sender is `cancelled` — and the sender is told, so he can open a dispute if it matters.
     */
    public function settleRun(TripRun $run): void
    {
        TripParcel::query()
            ->where('trip_run_id', $run->id)
            ->whereIn('status', [TripParcel::STATUS_ACCEPTED, TripParcel::STATUS_LOADED])
            ->get()
            ->each(function (TripParcel $p) {
                $p->update(['status' => $p->status === TripParcel::STATUS_LOADED ? TripParcel::STATUS_RETURNED : TripParcel::STATUS_CANCELLED]);
                $this->notify('trip_parcel_rejected', (int) $p->sender_id, (int) $p->business_id, $p->refresh(), 'open_trip_parcel');
            });
    }

    // -------------------------------------------------------------- helpers

    /** Compares a typed / scanned code, counting wrong tries (the handover locks after the shared maximum). */
    private function assertCode(TripParcel $parcel, string $attemptsColumn, string $hash, string $code): void
    {
        if ((int) $parcel->{$attemptsColumn} >= HandoverCode::MAX_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => __('تجاوزت عدد المحاولات. تواصل مع المرسل ليولّد كودًا جديدًا.')]);
        }

        if (! HandoverCode::matches($hash, $code)) {
            $parcel->increment($attemptsColumn);

            throw ValidationException::withMessages(['code' => __('الكود غير صحيح.')]);
        }
    }

    /** @param list<string> $allowed */
    private function assertStatus(TripParcel $parcel, array $allowed): void
    {
        if (! in_array($parcel->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __('لا يمكن تنفيذ هذا الإجراء على الشحنة في حالتها الحالية.')]);
        }
    }

    private function notify(string $eventKey, int $recipientId, int $actorId, TripParcel $parcel, string $actionType): void
    {
        if ($recipientId <= 0) {
            return;
        }

        try {
            $this->notifications->dispatch($eventKey, $recipientId, [
                'actor_id' => $actorId > 0 ? $actorId : null,
                'notifiable_type' => TripParcel::class,
                'notifiable_id' => (int) $parcel->id,
                'source_id' => (int) $parcel->id,
                'service_type' => 'schedules',
                // taps open the parcel itself (the carrier's list, or the sender's / receiver's)
                'action_type' => $actionType,
                'action_url' => '/schedules/parcels/'.$parcel->id,
                'meta' => ['trip_schedule_id' => (int) $parcel->trip_schedule_id],
            ]);
        } catch (\Throwable $e) {
            // best-effort, like the reservation notifications
        }
    }
}
