<?php

namespace App\Services\Schedules;

use App\Models\TripParcel;
use App\Models\TripSchedule;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Parcels riding with the car. The driver prices, the sender accepts, and the handover needs the code — so a driver
 * cannot claim a parcel was delivered without the receiver having given it to him.
 */
final class TripParcelService
{
    public function __construct(
        private readonly TripReservationService $reservations,
        private readonly NotificationDispatcherService $notifications,
    ) {}

    /**
     * @param  array{description:string, receiver_user_id?:?int, receiver_name?:?string, receiver_phone?:?string}  $data
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

        $parcel = TripParcel::create([
            'trip_schedule_id' => (int) $schedule->id,
            'business_id' => (int) $schedule->business_id,
            'sender_id' => (int) $sender->id,
            'receiver_user_id' => $receiverId,
            'receiver_name' => $receiverId === null ? trim((string) $data['receiver_name']) : null,
            'receiver_phone' => $receiverId === null ? trim((string) $data['receiver_phone']) : null,
            'description' => trim((string) $data['description']),
            'currency' => (string) ($schedule->currency ?: 'EGP'),
            'status' => TripParcel::STATUS_REQUESTED,
        ]);

        $this->notify('trip_parcel_requested', (int) $parcel->business_id, (int) $sender->id, $parcel);

        return $parcel;
    }

    /** The driver sets the price — and how many seats the parcel takes (0 when it rides in the boot). */
    public function price(TripParcel $parcel, float $price, int $seats = 0): TripParcel
    {
        $this->assertStatus($parcel, [TripParcel::STATUS_REQUESTED, TripParcel::STATUS_PRICED]);

        if ($price < 0) {
            throw ValidationException::withMessages(['price' => __('سعر الشحنة غير صالح.')]);
        }

        $parcel->update([
            'price' => round($price, 2),
            'seats' => max(0, $seats),
            'status' => TripParcel::STATUS_PRICED,
        ]);

        $this->notify('trip_parcel_priced', (int) $parcel->sender_id, (int) $parcel->business_id, $parcel);

        return $parcel->refresh();
    }

    /** The sender takes the driver's price; a parcel that takes seats claims them now. */
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

            $parcel->update(['status' => TripParcel::STATUS_ACCEPTED]);
        });

        // a parcel that takes the last seats fills the trip: a no-show's held deposit goes back
        $this->reservations->releaseNoShowDepositsIfFull((int) $parcel->trip_schedule_id);

        $this->notify('trip_parcel_accepted', (int) $parcel->business_id, (int) $parcel->sender_id, $parcel);

        return $parcel->refresh();
    }

    /**
     * The sender's button: a fresh code (4 digits + 2 letters), shown to him (and to a receiver who has an account, as a
     * QR). A code generated again replaces the old one until the parcel is on the car.
     */
    public function generateCode(TripParcel $parcel): TripParcel
    {
        $this->assertStatus($parcel, [
            TripParcel::STATUS_REQUESTED,
            TripParcel::STATUS_PRICED,
            TripParcel::STATUS_ACCEPTED,
        ]);

        $digits = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $letters = '';

        foreach (range(1, 2) as $_) {
            $letters .= chr(random_int(ord('a'), ord('z')));
        }

        $code = $digits.$letters;

        $parcel->update([
            'delivery_code' => $code,
            'delivery_code_hash' => $this->hash($code),
            'code_attempts' => 0,
        ]);

        return $parcel->refresh();
    }

    /** On the car. Needs a code to exist, so the handover can never be skipped. */
    public function load(TripParcel $parcel): TripParcel
    {
        $this->assertStatus($parcel, [TripParcel::STATUS_ACCEPTED]);

        if (! $parcel->delivery_code_hash) {
            throw ValidationException::withMessages(['code' => __('اطلب من المرسل توليد كود التسليم قبل تحميل الشحنة.')]);
        }

        $parcel->update(['status' => TripParcel::STATUS_LOADED, 'loaded_at' => now()]);

        return $parcel->refresh();
    }

    /** Handed over: the driver types the code the receiver told him. */
    public function deliver(TripParcel $parcel, string $code, int $driverId): TripParcel
    {
        $this->assertStatus($parcel, [TripParcel::STATUS_LOADED]);

        if ((int) $parcel->code_attempts >= TripParcel::MAX_CODE_ATTEMPTS) {
            throw ValidationException::withMessages(['code' => __('تجاوزت عدد المحاولات. تواصل مع المرسل ليولّد كودًا جديدًا.')]);
        }

        if (! hash_equals((string) $parcel->delivery_code_hash, $this->hash($code))) {
            $parcel->increment('code_attempts');

            throw ValidationException::withMessages(['code' => __('الكود غير صحيح.')]);
        }

        $parcel->update([
            'status' => TripParcel::STATUS_DELIVERED,
            'delivered_at' => now(),
            'delivered_by' => $driverId,
        ]);

        $this->notify('trip_parcel_delivered', (int) $parcel->sender_id, (int) $parcel->business_id, $parcel);

        return $parcel->refresh();
    }

    public function reject(TripParcel $parcel): TripParcel
    {
        $this->assertStatus($parcel, [TripParcel::STATUS_REQUESTED, TripParcel::STATUS_PRICED]);
        $parcel->update(['status' => TripParcel::STATUS_REJECTED]);
        $this->notify('trip_parcel_rejected', (int) $parcel->sender_id, (int) $parcel->business_id, $parcel);

        return $parcel->refresh();
    }

    public function cancel(TripParcel $parcel): TripParcel
    {
        $this->assertStatus($parcel, [TripParcel::STATUS_REQUESTED, TripParcel::STATUS_PRICED, TripParcel::STATUS_ACCEPTED]);
        $parcel->update(['status' => TripParcel::STATUS_CANCELLED]);

        return $parcel->refresh();
    }

    private function hash(string $code): string
    {
        return hash('sha256', mb_strtolower(trim($code)));
    }

    /** @param list<string> $allowed */
    private function assertStatus(TripParcel $parcel, array $allowed): void
    {
        if (! in_array($parcel->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => __('لا يمكن تنفيذ هذا الإجراء على الشحنة في حالتها الحالية.')]);
        }
    }

    private function notify(string $eventKey, int $recipientId, int $actorId, TripParcel $parcel): void
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
                'meta' => ['trip_schedule_id' => (int) $parcel->trip_schedule_id],
            ]);
        } catch (\Throwable $e) {
            // best-effort, like the reservation notifications
        }
    }
}
