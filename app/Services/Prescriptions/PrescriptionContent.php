<?php

namespace App\Services\Prescriptions;

use App\Models\Prescription;

/**
 * «الروشتة تُحفظ على الفون وتُعرض للصيدلي» — المالك، 2026-10-05. A copy of a prescription that lives on the
 * patient's phone has to be provably the doctor's, or anyone could type one. So the server keeps a fingerprint
 * of what the doctor wrote — a SHA-256 over a canonical form of its content — and can say, for any copy shown
 * to a pharmacist, «this is exactly what Dr. X issued» without the copy ever leaving the phone.
 *
 * The canonical form is deliberately boring: every value a string (or null), keys sorted, the items in the
 * order written. A JSON round trip through any client cannot change it.
 */
final class PrescriptionContent
{
    private const ITEM_FIELDS = ['medicine_id', 'name', 'dosage', 'quantity', 'instructions', 'frequency_per_day', 'food_timing', 'duration_days'];

    /** The canonical content of a stored prescription (needs its items). */
    public function of(Prescription $p): array
    {
        $p->loadMissing('items');

        return $this->canonical([
            'id' => $p->id,
            'issued_at' => optional($p->issued_at)->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'doctor_id' => $p->doctor_id,
            'patient_id' => $p->patient_id,
            'diagnosis' => $p->diagnosis,
            'patient_condition' => $p->patient_condition,
            'notes' => $p->notes,
            'items' => $p->items->sortBy('id')->map(fn ($i) => [
                'medicine_id' => $i->medicine_id, 'name' => $i->name, 'dosage' => $i->dosage, 'quantity' => $i->quantity,
                'instructions' => $i->instructions, 'frequency_per_day' => $i->frequency_per_day,
                'food_timing' => $i->food_timing, 'duration_days' => $i->duration_days,
            ])->values()->all(),
        ]);
    }

    /** Normalise any content a client sends back into the same canonical form. */
    public function canonical(array $content): array
    {
        $scalar = fn ($v) => $v === null || (is_string($v) && trim($v) === '') ? null : trim((string) $v);

        $items = [];
        foreach ((array) ($content['items'] ?? []) as $item) {
            $row = [];
            foreach (self::ITEM_FIELDS as $field) {
                $row[$field] = $scalar(((array) $item)[$field] ?? null);
            }
            $items[] = $row;
        }

        return [
            'v' => '1',
            'id' => $scalar($content['id'] ?? null),
            'issued_at' => $scalar($content['issued_at'] ?? null),
            'doctor_id' => $scalar($content['doctor_id'] ?? null),
            'patient_id' => $scalar($content['patient_id'] ?? null),
            'diagnosis' => $scalar($content['diagnosis'] ?? null),
            'patient_condition' => $scalar($content['patient_condition'] ?? null),
            'notes' => $scalar($content['notes'] ?? null),
            'items' => $items,
        ];
    }

    public function hash(array $content): string
    {
        return hash('sha256', json_encode($this->canonical($content), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Stamp the fingerprint on a doctor-issued prescription (after its items exist). */
    public function stamp(Prescription $p): void
    {
        $p->forceFill(['content_hash' => $this->hash($this->of($p))])->save();
    }
}
