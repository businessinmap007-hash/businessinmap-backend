<?php

namespace App\Services\Schedules;

use App\Models\TripRun;
use App\Models\TripRunManifestItem;
use App\Models\TripRunStop;
use App\Models\TripSchedule;
use App\Models\User;
use App\Services\Notifications\NotificationDispatcherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Live execution of a trip leg: start a run (snapshotting the schedule's
 * stops), cycle through them one at a time (heading -> arrived -> done), and
 * reconcile at the end — a manual cargo tally for freight/distribution, or
 * nothing further for passenger/limousine (everyone is treated as completing
 * together at the final stop). See create_trip_runs migration for why.
 */
final class TripRunService
{
    private const FREIGHT_MODES = [TripSchedule::MODE_FREIGHT, TripSchedule::MODE_DISTRIBUTION];
    private const PASSENGER_MODES = [TripSchedule::MODE_PASSENGER, TripSchedule::MODE_LIMOUSINE];

    public function __construct(
        private readonly NotificationDispatcherService $notifications
    ) {}

    /**
     * @param  list<array{label:string, unit?:?string, assigned_qty:int}>  $manifestLines
     */
    public function start(TripSchedule $schedule, User $actor, ?int $passengerCount, array $manifestLines): TripRun
    {
        $isFreight = in_array($schedule->mode, self::FREIGHT_MODES, true);
        $isPassenger = in_array($schedule->mode, self::PASSENGER_MODES, true);

        if ($isPassenger && ($passengerCount === null || $passengerCount < 1)) {
            throw ValidationException::withMessages(['passenger_count' => __('أدخل عدد الركاب الفعلي.')]);
        }

        if (! $isPassenger && $passengerCount !== null) {
            throw ValidationException::withMessages(['passenger_count' => __('عدد الركاب غير مطلوب لهذا النمط.')]);
        }

        if ($isFreight && empty($manifestLines)) {
            throw ValidationException::withMessages(['manifest' => __('أضف قائمة المنتجات المحمّلة قبل بدء الرحلة.')]);
        }

        if (! $isFreight && ! empty($manifestLines)) {
            throw ValidationException::withMessages(['manifest' => __('قائمة المنتجات غير مطلوبة لهذا النمط.')]);
        }

        $stops = $schedule->stops()->orderBy('sequence')->get();

        if ($stops->isEmpty()) {
            throw ValidationException::withMessages(['stops' => __('أضف نقاط توقف لهذا الخط قبل بدء تنفيذ الرحلة.')]);
        }

        return DB::transaction(function () use ($schedule, $actor, $passengerCount, $manifestLines, $stops) {
            $run = TripRun::create([
                'trip_schedule_id' => (int) $schedule->id,
                'business_id' => (int) $schedule->business_id,
                'started_by' => (int) $actor->id,
                'status' => TripRun::STATUS_IN_PROGRESS,
                'passenger_count' => $passengerCount,
                'started_at' => now(),
            ]);

            foreach ($stops as $i => $stop) {
                $run->stops()->create([
                    'trip_stop_id' => (int) $stop->id,
                    'sequence' => $i,
                    'label' => $stop->label,
                    'address' => $stop->address,
                    'status' => $i === 0 ? TripRunStop::STATUS_HEADING : TripRunStop::STATUS_PENDING,
                ]);
            }

            foreach ($manifestLines as $line) {
                TripRunManifestItem::create([
                    'trip_run_id' => (int) $run->id,
                    'label' => (string) $line['label'],
                    'unit' => $line['unit'] ?? null,
                    'assigned_qty' => max(0, (int) $line['assigned_qty']),
                ]);
            }

            return $run->fresh(['stops', 'manifestItems', 'schedule:id,mode,vehicle_label']);
        });
    }

    /** The current (`heading`) stop has been reached. */
    public function markArrived(TripRun $run): TripRun
    {
        $current = $run->currentStop();

        if (! $current || $current->status !== TripRunStop::STATUS_HEADING) {
            throw ValidationException::withMessages(['run' => __('لا توجد نقطة يمكن تسجيل الوصول إليها الآن.')]);
        }

        $current->update(['status' => TripRunStop::STATUS_ARRIVED, 'arrived_at' => now()]);

        $this->notify($run, 'trip_run_stop_arrived', ['stop' => $current->label]);

        return $run->fresh(['stops', 'manifestItems', 'schedule:id,mode,vehicle_label']);
    }

    /**
     * Mark the current (`arrived`) stop done and move on. If it was the last
     * stop: freight/distribution goes to `awaiting_reconciliation`; anything
     * else completes right away.
     */
    public function advance(TripRun $run): TripRun
    {
        $current = $run->currentStop();

        if (! $current || $current->status !== TripRunStop::STATUS_ARRIVED) {
            throw ValidationException::withMessages(['run' => __('يجب تسجيل الوصول إلى النقطة الحالية أولًا.')]);
        }

        return DB::transaction(function () use ($run, $current) {
            $current->update(['status' => TripRunStop::STATUS_DONE, 'completed_at' => now()]);

            $next = $run->stops()->where('sequence', '>', $current->sequence)->orderBy('sequence')->first();

            if ($next) {
                $next->update(['status' => TripRunStop::STATUS_HEADING]);
                $this->notify($run, 'trip_run_stop_departed', ['stop' => $next->label]);

                return $run->fresh(['stops', 'manifestItems', 'schedule:id,mode,vehicle_label']);
            }

            $isFreight = in_array($run->schedule->mode, self::FREIGHT_MODES, true);

            $run->update([
                'status' => $isFreight ? TripRun::STATUS_AWAITING_RECONCILIATION : TripRun::STATUS_COMPLETED,
                'completed_at' => $isFreight ? null : now(),
            ]);

            if (! $isFreight) {
                $this->notify($run, 'trip_run_completed', []);
            }

            return $run->fresh(['stops', 'manifestItems', 'schedule:id,mode,vehicle_label']);
        });
    }

    /**
     * Freight/distribution only: record what actually happened to each
     * manifest line and complete the run.
     *
     * @param  array<int, array{delivered_qty:int, returned_qty:int}>  $items  keyed by manifest item id
     */
    public function reconcile(TripRun $run, array $items): TripRun
    {
        if ($run->status !== TripRun::STATUS_AWAITING_RECONCILIATION) {
            throw ValidationException::withMessages(['run' => __('هذه الرحلة ليست بانتظار تسوية المنتجات.')]);
        }

        return DB::transaction(function () use ($run, $items) {
            foreach ($run->manifestItems as $line) {
                $entry = $items[$line->id] ?? null;

                if ($entry === null) {
                    throw ValidationException::withMessages(['items' => __('أدخل بيانات كل صنف في القائمة.')]);
                }

                $delivered = max(0, (int) ($entry['delivered_qty'] ?? 0));
                $returned = max(0, (int) ($entry['returned_qty'] ?? 0));

                if ($delivered + $returned > (int) $line->assigned_qty) {
                    throw ValidationException::withMessages([
                        'items' => __('المُسلَّم والمرتجع لصنف ":label" أكبر من الكمية المحمّلة.', ['label' => $line->label]),
                    ]);
                }

                $line->update(['delivered_qty' => $delivered, 'returned_qty' => $returned]);
            }

            $run->update(['status' => TripRun::STATUS_COMPLETED, 'completed_at' => now()]);

            $this->notify($run, 'trip_run_completed', []);

            return $run->fresh(['stops', 'manifestItems', 'schedule:id,mode,vehicle_label']);
        });
    }

    private function notify(TripRun $run, string $eventKey, array $meta): void
    {
        try {
            $this->notifications->dispatch($eventKey, (int) $run->business_id, [
                'actor_id' => (int) $run->started_by,
                'notifiable_type' => TripRun::class,
                'notifiable_id' => (int) $run->id,
                'source_id' => (int) $run->id,
                'service_type' => 'schedules',
                'meta' => array_merge(['trip_schedule_id' => (int) $run->trip_schedule_id], $meta),
            ]);
        } catch (\Throwable $e) {
            // Notifications are best-effort; never break the run flow.
        }
    }
}
