<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\BodyCompositionReport;
use App\Models\TrainingPlan;
use App\Support\BusinessContext;
use Illuminate\Http\Request;

/**
 * The monthly body report: muscle, fat, water — one row a month, per plan.
 *
 * The plan already carried the client's own check-in (a weight and a note). That
 * cannot answer the question a training plan is actually judged on: whether the
 * weight that moved was muscle, fat or water. A client who loses three kilos of
 * water reads it as progress; his trainer needs to read it as a warning.
 *
 * **The trainer records, both parties read.** He owns the scale, and it is the
 * same one-way rule the plan's illustrative photos follow. The client's own
 * weight log stays exactly where it was — this does not replace it.
 *
 * **Private to the two of them.** Every route resolves the plan by (id + the
 * caller's own side) and 404s otherwise, never 403: a 403 would confirm that
 * this client trains with this captain, which is the fact being protected.
 */
final class BodyCompositionController extends Controller
{
    /** GET /api/v2/business/training-plans/{plan}/body-reports — the trainer's view. */
    public function trainerIndex(Request $request, int $plan)
    {
        return $this->series($this->ownedOrFail($request, $plan));
    }

    /** GET /api/v2/training-plans/{plan}/body-reports — the client's own. */
    public function clientIndex(Request $request, int $plan)
    {
        return $this->series($this->mineOrFail($request, $plan));
    }

    /**
     * POST /api/v2/business/training-plans/{plan}/body-reports
     *
     * One row per month: sending the same month twice UPDATES it rather than
     * filing a second reading, because «تقرير شهري» is one report and a scale
     * read twice in March is still March.
     */
    public function store(Request $request, int $plan)
    {
        $row = $this->ownedOrFail($request, $plan);

        $data = $request->validate([
            'for_month' => ['nullable', 'date'],
            'measured_on' => ['nullable', 'date'],
            'weight_kg' => ['nullable', 'numeric', 'min:1', 'max:600'],
            'muscle_mass_kg' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'fat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'water_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'bone_mass_kg' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'visceral_fat' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $measures = array_intersect_key($data, array_flip([
            'weight_kg', 'muscle_mass_kg', 'fat_percent', 'water_percent', 'bone_mass_kg', 'visceral_fat',
        ]));

        // A report of nothing is not a report — it would occupy the month and
        // read as «measured, all zero» to whoever sees the series next.
        if (collect($measures)->filter(fn ($v) => $v !== null)->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => __('سجّل قياسًا واحدًا على الأقل.'),
            ], 422);
        }

        // Fat and water are shares of the same body; together they cannot pass
        // 100. Nothing else catches this, and a typo here is a plan changed for
        // the wrong reason.
        $fat = $data['fat_percent'] ?? null;
        $water = $data['water_percent'] ?? null;

        if ($fat !== null && $water !== null && ($fat + $water) > 100) {
            return response()->json([
                'success' => false,
                'message' => __('نسبة الدهون والمياه معًا لا تتجاوز ١٠٠٪.'),
            ], 422);
        }

        $month = BodyCompositionReport::monthOf($data['for_month'] ?? null);

        $report = BodyCompositionReport::updateOrCreate(
            ['training_plan_id' => (int) $row->id, 'for_month' => $month->toDateString()],
            $measures + [
                'client_id' => (int) $row->client_id,
                'trainer_id' => (int) $row->trainer_id,
                'measured_on' => $data['measured_on'] ?? $month->toDateString(),
                'notes' => $data['notes'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => __('تم حفظ تقرير الشهر.'),
            'data' => ['report' => $this->payload($report, null)],
        ], $report->wasRecentlyCreated ? 201 : 200);
    }

    /** DELETE /api/v2/business/training-plans/{plan}/body-reports/{report} */
    public function destroy(Request $request, int $plan, int $report)
    {
        $row = $this->ownedOrFail($request, $plan);

        $row->bodyReports()->where('id', $report)->first()?->delete();

        return response()->json(['success' => true]);
    }

    /**
     * The series, newest first, each month carrying its change from the one
     * before — «-٢٫١ دهون +٠٫٨ عضل» rather than two cards and mental arithmetic.
     */
    private function series(TrainingPlan $plan)
    {
        $reports = $plan->bodyReports()->get();

        // Oldest→newest to compute deltas, then flipped back for display.
        $ordered = $reports->sortBy('for_month')->values();
        $out = [];

        foreach ($ordered as $i => $report) {
            $out[] = $this->payload($report, $i > 0 ? $ordered[$i - 1] : null);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'plan' => ['id' => (int) $plan->id, 'title' => (string) $plan->title],
                'reports' => array_reverse($out),
            ],
        ]);
    }

    /** @return array<string,mixed> */
    private function payload(BodyCompositionReport $report, ?BodyCompositionReport $previous): array
    {
        $number = fn ($v) => $v === null ? null : (float) $v;

        return [
            'id' => (int) $report->id,
            'for_month' => optional($report->for_month)->format('Y-m'),
            'measured_on' => optional($report->measured_on)->toDateString(),
            'weight_kg' => $number($report->weight_kg),
            'muscle_mass_kg' => $number($report->muscle_mass_kg),
            'fat_percent' => $number($report->fat_percent),
            'water_percent' => $number($report->water_percent),
            'bone_mass_kg' => $number($report->bone_mass_kg),
            'visceral_fat' => $number($report->visceral_fat),
            'notes' => $report->notes,
            'change' => $report->deltaFrom($previous),
        ];
    }

    private function ownedOrFail(Request $request, int $planId): TrainingPlan
    {
        return TrainingPlan::query()
            ->where('id', $planId)
            ->where('trainer_id', BusinessContext::id($request))
            ->firstOrFail();
    }

    private function mineOrFail(Request $request, int $planId): TrainingPlan
    {
        return TrainingPlan::query()
            ->where('id', $planId)
            ->where('client_id', (int) $request->user()->id)
            ->firstOrFail();
    }
}
