<?php

namespace App\Services\Prescriptions;

use App\Models\AppNotification;
use App\Models\Prescription;
use App\Services\DeliveryDispatchService;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The same connected delivery loop `DeliveryDispatchService` runs for menu
 * orders (driver accepts → scans the pharmacy's pickup QR → scans deliver to
 * the patient), mirrored onto `Prescription` instead of `Order`. A driver is
 * a driver either way — this reuses the SAME `delivery_drivers` pool and
 * `DeliveryDispatchService::driverOrFail()` for identity, rather than a
 * second registration.
 *
 * `Prescription` has no `prep_status` the way `Order` does; `status=ready`
 * (set by `PrescriptionService::markReady()`) is the equivalent gate — the
 * pharmacy has finished preparing it.
 *
 * Final delivery reuses `PrescriptionService::dispense()` rather than
 * setting `status` directly, so the "must be priced first" rule
 * ({@see PrescriptionService::dispense()}) applies here exactly as it does
 * to a pharmacy-direct (pickup) dispense — one rule, not two.
 */
class PrescriptionDeliveryService
{
    public function __construct(
        protected DeliveryDispatchService $baseDelivery,
        protected NotificationDispatcherService $notifications,
        protected PrescriptionService $prescriptions,
    ) {
    }

    /**
     * Ready, unassigned delivery prescriptions open for a driver to take. A
     * pharmacy's own private driver (delivery_drivers.business_id set) only
     * ever sees THAT pharmacy's own prescriptions — same rule as
     * DeliveryDispatchService::availableOrders() for menu orders.
     */
    public function availablePrescriptions(int $limit = 50, ?int $pharmacyId = null)
    {
        return Prescription::query()
            ->where('fulfillment_type', Prescription::FULFILLMENT_DELIVERY)
            ->where('status', Prescription::STATUS_READY)
            ->whereNull('delivery_driver_id')
            ->when($pharmacyId, fn ($q) => $q->where('pharmacy_id', $pharmacyId))
            ->with('pharmacy:id,name')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /** A driver takes a ready, unassigned delivery prescription. */
    public function acceptPrescription(int $userId, int $prescriptionId): Prescription
    {
        $driver = $this->baseDelivery->driverOrFail($userId);
        if (! $driver->is_active) {
            abort(403, __('حسابك كموصّل غير مفعّل.'));
        }

        $prescription = DB::transaction(function () use ($driver, $prescriptionId) {
            $row = Prescription::query()->lockForUpdate()->find($prescriptionId);

            if (! $row || (string) $row->fulfillment_type !== Prescription::FULFILLMENT_DELIVERY) {
                abort(404, __('طلب توصيل الوصفة غير موجود.'));
            }
            if ((string) $row->status !== Prescription::STATUS_READY || $row->delivery_driver_id) {
                abort(409, __('هذه الوصفة غير متاحة للاستلام.'));
            }
            if ($driver->business_id && (int) $driver->business_id !== (int) $row->pharmacy_id) {
                abort(403, __('هذه الوصفة لا تخص نشاطك.'));
            }

            $row->delivery_driver_id = $driver->id;
            $row->delivery_stage = DeliveryDispatchService::STAGE_ASSIGNED;
            $row->save();

            $driver->increment('assigned_count');

            return $row;
        });

        $this->notifyPharmacy($prescription, 'delivery_assigned', $userId, [
            'body_ar' => 'قبِل موصّل توصيل وصفة طبية رقم #' . $prescription->id . '.',
            'body_en' => 'A driver accepted delivery of prescription #' . $prescription->id . '.',
        ]);

        return $prescription;
    }

    /** The pharmacy issues the one-time pickup token (shown to the driver). */
    public function issuePickupToken(Prescription $prescription, int $pharmacyUserId): string
    {
        if ((int) $prescription->pharmacy_id !== $pharmacyUserId) {
            abort(403, __('لست صيدلية هذه الوصفة.'));
        }
        if ((string) $prescription->delivery_stage !== DeliveryDispatchService::STAGE_ASSIGNED) {
            throw ValidationException::withMessages([
                'prescription' => __('الوصفة غير جاهزة لتسليمها للموصّل.'),
            ]);
        }

        if (! $prescription->pickup_token) {
            $prescription->pickup_token = Str::random(48);
            $prescription->save();
        }

        return (string) $prescription->pickup_token;
    }

    /** The assigned driver scans the pharmacy's pickup QR → picked_up. */
    public function confirmPickup(string $token, int $byUserId): Prescription
    {
        return DB::transaction(function () use ($token, $byUserId) {
            $prescription = Prescription::query()->where('pickup_token', $token)->lockForUpdate()->first();
            if (! $prescription) {
                abort(404, __('رمز الاستلام غير صالح أو تم استخدامه.'));
            }

            $driver = $prescription->deliveryDriver;
            if (! $driver || (int) $driver->user_id !== $byUserId) {
                abort(403, __('هذه الوصفة غير مُسندة إليك.'));
            }
            if ((string) $prescription->delivery_stage !== DeliveryDispatchService::STAGE_ASSIGNED) {
                abort(409, __('لا يمكن تأكيد الاستلام في هذه المرحلة.'));
            }

            $prescription->delivery_stage = DeliveryDispatchService::STAGE_PICKED_UP;
            $prescription->pickup_token = null; // consume
            $prescription->save();

            $driver->increment('picked_up_count');

            return $prescription;
        });
    }

    /** The assigned driver issues the one-time delivery token (shown to the patient). */
    public function issueDeliveryToken(int $prescriptionId, int $driverUserId): Prescription
    {
        $prescription = Prescription::query()->findOrFail($prescriptionId);

        $driver = $prescription->deliveryDriver;
        if (! $driver || (int) $driver->user_id !== $driverUserId) {
            abort(403, __('هذه الوصفة غير مُسندة إليك.'));
        }
        if ((string) $prescription->delivery_stage !== DeliveryDispatchService::STAGE_PICKED_UP) {
            throw ValidationException::withMessages([
                'prescription' => __('لم يتم استلام الوصفة من الصيدلية بعد.'),
            ]);
        }

        if (! $prescription->delivery_token) {
            $prescription->delivery_token = Str::random(48);
            $prescription->save();
        }

        return $prescription;
    }

    /**
     * The patient scans the driver's delivery QR → delivered, and the
     * prescription itself moves to dispensed through the one existing rule
     * for that (must already be priced).
     */
    public function confirmDelivery(string $token, int $byUserId): Prescription
    {
        $prescription = DB::transaction(function () use ($token, $byUserId) {
            $row = Prescription::query()->where('delivery_token', $token)->lockForUpdate()->first();
            if (! $row) {
                abort(404, __('رمز التسليم غير صالح أو تم استخدامه.'));
            }
            if ((int) $row->patient_id !== $byUserId) {
                abort(403, __('هذه الوصفة ليست لك.'));
            }
            if ((string) $row->delivery_stage !== DeliveryDispatchService::STAGE_PICKED_UP) {
                abort(409, __('لا يمكن تأكيد التسليم في هذه المرحلة.'));
            }

            $driver = $row->deliveryDriver;

            $row->delivery_stage = DeliveryDispatchService::STAGE_DELIVERED;
            $row->delivery_token = null; // consume
            $row->save();

            if ($driver) {
                $driver->increment('delivered_count');
            }

            // The one rule for "dispensed" (must be priced) — not
            // re-implemented here, and run in the SAME transaction: an
            // unpriced prescription must roll back the scan too, not end up
            // "delivered" on the driver's side and stuck on "ready" on its own.
            return $this->prescriptions->dispense($row);
        });

        $this->notifyPharmacy($prescription, 'prescription_delivered', $byUserId, [
            'body_ar' => 'تم تسليم الوصفة رقم #' . $prescription->id . ' بنجاح.',
            'body_en' => 'Prescription #' . $prescription->id . ' was delivered successfully.',
        ]);

        return $prescription;
    }

    /** Notify the prescription's pharmacy. Best-effort. */
    private function notifyPharmacy(Prescription $prescription, string $eventKey, int $actorId, array $data): void
    {
        $pharmacyId = (int) $prescription->pharmacy_id;
        if ($pharmacyId <= 0) {
            return;
        }

        try {
            $this->notifications->dispatch($eventKey, $pharmacyId, array_merge([
                'type' => AppNotification::TYPE_SYSTEM,
                'actor_id' => $actorId,
                'notifiable_type' => Prescription::class,
                'notifiable_id' => (int) $prescription->id,
                'source_id' => (int) $prescription->id,
                'meta' => [
                    'prescription_id' => (int) $prescription->id,
                    'delivery_driver_id' => (int) $prescription->delivery_driver_id,
                ],
            ], $data));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
