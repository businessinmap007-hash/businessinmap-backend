<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A residential building in one go (owner, 2026-10-20: five floors and a basement, three flats a floor, supervised by an
 * engineering office). THREE main stages, as the owner divided the work:
 *
 *  1. التجهيز والإنشاءات — excavation, plain concrete, foundations, then the concrete frame (columns and slab) and the masonry
 *     level by level, and the stairs;
 *  2. التأسيس — electrical, plumbing and air-conditioning piping flat by flat, safety and cameras level by level, gas and
 *     intercom/networks for the building;
 *  3. التشطيبات — plaster, gypsum, tiles, marble, doors and windows, paint, air-conditioning units and fixtures flat by flat,
 *     the GRP façade, then cleaning and the final handover.
 *
 * Inside them every work has its place written on it («الدور الثالث / شقة ٢»), a weight, and the quantities an engineer fills
 * in (columns, concrete, bricks, areas). Each main stage waits for the one before. All of it is an ordinary plan afterwards: the
 * engineer edits, adds and removes freely until the plan is agreed.
 */
final class BuildingProjectGenerator
{
    private const ORDINALS = ['الأرضي', 'الدور الأول', 'الدور الثاني', 'الدور الثالث', 'الدور الرابع', 'الدور الخامس', 'الدور السادس', 'الدور السابع', 'الدور الثامن', 'الدور التاسع', 'الدور العاشر'];

    private int $order = 0;
    private int $made = 0;
    private ?ProjectTask $previousPhase = null;

    public function __construct(private readonly ProjectService $projects)
    {
    }

    /**
     * @param  array{floors:int,basement?:bool,apartments_per_floor:int,elevator?:bool}  $opts  `floors` counts the ground floor; `elevator` (default true) adds the lift's three works
     * @return int the number of works written
     */
    public function generate(Project $project, array $opts, ?Carbon $start = null): int
    {
        $this->order = 0;
        $this->made = 0;
        $this->previousPhase = null;

        $floors = max(1, min(30, (int) $opts['floors']));
        $flats = max(0, min(10, (int) $opts['apartments_per_floor']));
        $elevator = ! array_key_exists('elevator', $opts) || (bool) $opts['elevator'];

        $levels = [];
        if (! empty($opts['basement'])) {
            $levels[] = ['name' => 'البدروم', 'flats' => 0];
        }
        for ($i = 0; $i < $floors; $i++) {
            $levels[] = ['name' => self::ORDINALS[$i] ?? ('الدور ' . $i), 'flats' => $flats];
        }

        $first = ($start ?? ($project->starts_on ? Carbon::parse($project->starts_on) : Carbon::today()))->copy()->startOfDay();

        DB::transaction(function () use ($project, $levels, $first, $elevator) {
            $day = $first->copy();

            // ---------------------------------------------------------------- 1 — preparation and construction
            $phase = $this->phase($project, 'مرحلة التجهيز والإنشاءات');
            $last = null;
            foreach ([['الحفر', 4, []], ['الخرسانة العادية', 3, [['الخرسانة', 'م³']]], ['القواعد والأساسات', 10, [['الخرسانة', 'م³'], ['حديد التسليح', 'طن'], ['عدد القواعد', 'قاعدة']]]] as [$title, $days, $items]) {
                [$last, $end] = $this->work($project, $phase, $title, 'المبنى', $days, $day, $items, $last);
                $day = $end->copy()->addDay();
            }

            foreach ($levels as $level) {
                [$columns, $end] = $this->work($project, $phase, 'الأعمدة', $level['name'], 6, $day, [['عدد الأعمدة', 'عمود'], ['الخرسانة', 'م³'], ['حديد التسليح', 'طن']], $last);
                [$last, $end] = $this->work($project, $phase, 'السقف', $level['name'], 8, $end->copy()->addDay(), [['الخرسانة', 'م³'], ['حديد التسليح', 'طن']], $columns);
                $day = $end->copy()->addDay();
            }

            foreach ($levels as $level) {
                [$last, $end] = $this->work($project, $phase, 'مباني الطوب', $level['name'], 7, $day, [['الطوب', 'طوبة'], ['الأسمنت', 'كيس']], $last);
                $day = $end->copy()->addDay();
            }

            [$stairs, $end] = $this->work($project, $phase, 'السلالم', 'المبنى', 8, $day, [['الخرسانة', 'م³'], ['حديد التسليح', 'طن']], $last);
            $day = $end->copy()->addDay();

            if ($elevator) {
                [, $end] = $this->work($project, $phase, 'بئر المصعد', 'المبنى', 6, $day, [['الخرسانة', 'م³'], ['حديد التسليح', 'طن'], ['عدد الوقفات', 'وقفة']], $stairs);
                $day = $end->copy()->addDay();
            }

            // ---------------------------------------------------------------- 2 — installations
            $phase = $this->phase($project, 'مرحلة التأسيس');
            $from = $day->copy();
            $installEnd = $from->copy();

            foreach ($levels as $level) {
                for ($f = 1; $f <= $level['flats']; $f++) {
                    $zone = $level['name'] . ' / شقة ' . $f;
                    $at = $from->copy();
                    $last = null;
                    foreach ([['الكهرباء', 5, [['نقاط الكهرباء', 'نقطة']]], ['السباكة', 5, [['نقاط المياه والصرف', 'نقطة']]], ['تأسيس التكييف', 3, [['عدد الوحدات', 'وحدة']]]] as [$title, $days, $items]) {
                        [$last, $end] = $this->work($project, $phase, $title, $zone, $days, $at, $items, $last);
                        $at = $end->copy()->addDay();
                    }
                    $installEnd = $at->gt($installEnd) ? $at->copy() : $installEnd;
                }

                [, $end] = $this->work($project, $phase, 'سيفتى وكاميرات', $level['name'], 4, $from, [['الكاميرات', 'كاميرا'], ['كواشف ومطافئ الحريق', 'قطعة']]);
                $installEnd = $end->copy()->addDay()->gt($installEnd) ? $end->copy()->addDay() : $installEnd;
            }

            if ($elevator) {
                [, $end] = $this->work($project, $phase, 'تأسيس المصعد (القضبان والكهرباء)', 'المبنى', 7, $from, [['القضبان', 'متر'], ['عدد الوقفات', 'وقفة']]);
                $installEnd = $end->copy()->addDay()->gt($installEnd) ? $end->copy()->addDay() : $installEnd;
            }

            [, $end] = $this->work($project, $phase, 'شبكة الغاز', 'المبنى', 4, $from, [['نقاط الغاز', 'نقطة']]);
            $installEnd = $end->copy()->addDay()->gt($installEnd) ? $end->copy()->addDay() : $installEnd;
            [, $end] = $this->work($project, $phase, 'الانتركم وشبكات الاتصال', 'المبنى', 3, $from, [['النقاط', 'نقطة']]);
            $installEnd = $end->copy()->addDay()->gt($installEnd) ? $end->copy()->addDay() : $installEnd;

            // ---------------------------------------------------------------- 3 — finishing
            $phase = $this->phase($project, 'مرحلة التشطيبات');
            $day = $installEnd->copy();
            $finishEnd = $day->copy();

            $trades = [
                ['المحارة', 7, [['المساحة', 'م²']]],
                ['الجبس والأسقف', 4, [['المساحة', 'م²']]],
                ['السيراميك', 7, [['المساحة', 'م²']]],
                ['الرخام', 4, [['المساحة', 'م²']]],
                ['الأبواب والشبابيك', 4, [['عدد الأبواب والشبابيك', 'قطعة']]],
                ['النقاشة والدهانات', 6, [['المساحة', 'م²']]],
                ['تركيب وحدات التكييف', 2, [['عدد الوحدات', 'وحدة']]],
                ['تركيب الأدوات الصحية والكهربائية', 3, [['العدد', 'قطعة']]],
            ];

            if ($elevator) {
                [, $end] = $this->work($project, $phase, 'تركيب وتشغيل المصعد والترخيص', 'المبنى', 10, $day, [['عدد المصاعد', 'مصعد'], ['عدد الوقفات', 'وقفة']]);
                $finishEnd = $end->copy()->addDay()->gt($finishEnd) ? $end->copy()->addDay() : $finishEnd;
            }

            [, $end] = $this->work($project, $phase, 'GRP والواجهات', 'المبنى', 10, $day, [['المساحة', 'م²']]);
            $finishEnd = $end->copy()->addDay()->gt($finishEnd) ? $end->copy()->addDay() : $finishEnd;

            foreach ($levels as $level) {
                for ($f = 1; $f <= $level['flats']; $f++) {
                    $zone = $level['name'] . ' / شقة ' . $f;
                    $at = $day->copy();
                    $last = null;
                    foreach ($trades as [$title, $days, $items]) {
                        [$last, $end] = $this->work($project, $phase, $title, $zone, $days, $at, $items, $last);
                        $at = $end->copy()->addDay();
                    }
                    $finishEnd = $at->gt($finishEnd) ? $at->copy() : $finishEnd;
                }
            }

            $this->work($project, $phase, 'التنظيف والتسليم النهائي', 'المبنى', 3, $finishEnd->copy());
        });

        $fresh = $project->fresh();
        $this->projects->recomputeProgress($fresh);
        $fresh->forceFill(['starts_on' => $fresh->starts_on ?: $first->toDateString()])->save();

        return $this->made;
    }

    /** A main stage; it waits for the one before it and asks the customer's approval when it is done. */
    private function phase(Project $project, string $title): ProjectTask
    {
        $phase = $project->tasks()->create([
            'title' => $title, 'status' => ProjectTask::STATUS_PENDING, 'progress' => 0, 'requires_photo' => false,
            'requires_approval' => true, 'sort_order' => ++$this->order,
        ]);

        if ($this->previousPhase) {
            $this->projects->setDependencies($phase, [(int) $this->previousPhase->id]);
        }

        return $this->previousPhase = $phase;
    }

    /**
     * One work inside a phase.
     *
     * @param  list<array{0:string,1:string}>  $items  [name, unit] — planned amounts start at zero for the engineer to fill
     * @return array{0:ProjectTask,1:Carbon} the work and the day it ends
     */
    private function work(Project $project, ProjectTask $phase, string $title, ?string $zone, int $days, Carbon $from, array $items = [], ?ProjectTask $after = null): array
    {
        $to = $from->copy()->addDays(max(1, $days) - 1);

        $task = $project->tasks()->create([
            'parent_id' => $phase->id, 'title' => $title, 'zone' => $zone, 'weight' => $days, 'status' => ProjectTask::STATUS_PENDING, 'progress' => 0,
            'starts_on' => $from->toDateString(), 'ends_on' => $to->toDateString(), 'requires_photo' => true, 'requires_approval' => false,
            'sort_order' => ++$this->order,
        ]);

        foreach ($items as $n => [$name, $unit]) {
            $task->items()->create(['name' => $name, 'unit' => $unit, 'planned' => 0, 'done' => 0, 'sort_order' => $n + 1]);
        }

        if ($after) {
            $this->projects->setDependencies($task, [(int) $after->id]);
        }

        $this->made++;

        return [$task, $to];
    }
}
