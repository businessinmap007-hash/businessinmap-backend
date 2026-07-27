<?php

namespace App\Services\Agenda;

use App\Models\AgendaItem;
use App\Models\MealSchedule;
use App\Models\Prescription;
use App\Models\PrescriptionItem;
use Illuminate\Support\Carbon;

/**
 * Turns a prescription's structured dosage into agenda medication reminders,
 * timed off the patient's meal times. The patient triggers this after setting
 * (or confirming) their meal times; re-running replaces the prior schedule.
 */
class MedicationScheduleService
{
    /** Cap the horizon so one prescription can't flood the agenda. */
    private const MAX_DAYS = 30;

    /** Minutes offset from a meal for before/with/after. */
    private const FOOD_OFFSET = ['before' => -30, 'with' => 0, 'after' => 30];

    public function __construct(private readonly AgendaService $agenda)
    {
    }

    /**
     * (Re)build the medication reminders for a prescription. Returns how many
     * dose reminders were placed on the patient's agenda.
     */
    public function schedule(Prescription $prescription): int
    {
        $patientId = (int) $prescription->patient_id;
        $meals = MealSchedule::query()->firstOrNew(['user_id' => $patientId]);

        $this->clearExisting($prescription);

        $now = Carbon::now();
        $placed = 0;

        foreach ($prescription->items as $item) {
            foreach ($this->slotsFor($item) as $slot) {
                [$h, $m] = $this->slotClock($item, $meals, $slot);
                $days = min(max((int) ($item->duration_days ?: 7), 1), self::MAX_DAYS);

                for ($d = 0; $d < $days; $d++) {
                    $at = $now->copy()->addDays($d)->setTime($h, $m, 0);
                    if ($at->lte($now)) {
                        continue; // skip a dose whose time already passed today
                    }

                    $this->agenda->addReminder($patientId, AgendaItem::KIND_MEDICATION, $item,
                        $this->doseTitle($item), $at);
                    $placed++;
                }
            }
        }

        return $placed;
    }

    /** Remove a prescription's previously generated medication reminders. */
    private function clearExisting(Prescription $prescription): void
    {
        $itemIds = $prescription->items->pluck('id')->all();
        if ($itemIds === []) {
            return;
        }

        AgendaItem::query()
            ->where('source_type', (new PrescriptionItem())->getMorphClass())
            ->whereIn('source_id', $itemIds)
            ->where('kind', AgendaItem::KIND_MEDICATION)
            ->delete();
    }

    /** The day-slots to take a medicine in — explicit, else derived from frequency. */
    private function slotsFor(PrescriptionItem $item): array
    {
        $slots = is_array($item->time_slots) ? array_values(array_filter($item->time_slots)) : [];
        if ($slots !== []) {
            return $slots;
        }

        return match ((int) $item->frequency_per_day) {
            1 => ['dinner'],
            2 => ['breakfast', 'dinner'],
            3 => ['breakfast', 'lunch', 'dinner'],
            4 => ['breakfast', 'lunch', 'dinner', 'evening'],
            default => [],
        };
    }

    /** Hour/minute for a slot: a meal time (with food offset) or a fixed morning/evening. */
    private function slotClock(PrescriptionItem $item, MealSchedule $meals, string $slot): array
    {
        $time = Carbon::createFromFormat('H:i:s', $meals->timeFor($slot));

        // Food offset only applies to the meal slots, not morning/evening markers.
        if (in_array($slot, ['breakfast', 'lunch', 'dinner'], true) && $item->food_timing) {
            $time->addMinutes(self::FOOD_OFFSET[$item->food_timing] ?? 0);
        }

        return [(int) $time->format('H'), (int) $time->format('i')];
    }

    private function doseTitle(PrescriptionItem $item): string
    {
        return trim($item->name . ' ' . (string) $item->dosage);
    }
}
