<?php

namespace App\Console\Commands;

use App\Models\Prescription;
use Illuminate\Console\Command;

/**
 * «احذف الحقول الحساسة فقط وابقِ الأصناف» — المالك، 2026-10-05. The diagnosis, the patient's condition and the
 * doctor's notes of a FINISHED prescription leave the server once, and only if:
 *   • it is dispensed or cancelled, and has been for at least `--days` (90 by default);
 *   • it carries the doctor's fingerprint (so the phone's copy can still be proved authentic);
 *   • the patient's phone confirmed it holds the exact content (`archived_by_patient_at`).
 * The medicine lines, the fingerprint, the dates and the price stay. It is permanent: the patient's phone (and
 * his encrypted backup) is where the sensitive part lives from then on.
 */
class PurgePrescriptionSensitiveFields extends Command
{
    protected $signature = 'prescriptions:purge-sensitive {--days=90 : how long after it finished} {--dry-run : count, change nothing}';

    protected $description = 'Remove the diagnosis, condition and notes of finished prescriptions the patient\'s phone already holds.';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);

        $query = Prescription::query()
            ->whereIn('status', [Prescription::STATUS_DISPENSED, Prescription::STATUS_CANCELLED])
            ->whereNotNull('content_hash')
            ->whereNotNull('archived_by_patient_at')
            ->whereNull('content_purged_at')
            // finished = dispensed_at for a dispensed one, else the last change (a cancel only touches updated_at)
            ->whereRaw('COALESCE(dispensed_at, updated_at) <= ?', [$cutoff]);

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Would purge the sensitive fields of {$count} prescription(s) finished before {$cutoff->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $purged = 0;
        $query->chunkById(200, function ($rows) use (&$purged) {
            foreach ($rows as $row) {
                // forceFill + saveQuietly: a bulk housekeeping job must not look like a doctor's amendment
                // (no updated_at bump that would move the «finished» clock, no model events).
                $row->timestamps = false;
                $row->forceFill([
                    'diagnosis' => null,
                    'patient_condition' => null,
                    'notes' => null,
                    'content_purged_at' => now(),
                ])->saveQuietly();
                $purged++;
            }
        });

        $this->info("Purged the sensitive fields of {$purged} prescription(s).");

        return self::SUCCESS;
    }
}
