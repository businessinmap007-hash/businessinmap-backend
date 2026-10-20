<?php

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A residential building in one go (owner, 2026-10-20: five floors and a basement, three flats a floor, supervised by an
 * engineering office). Six main stages — foundations, the concrete frame, masonry, installations, finishing, handover — and,
 * inside them, a work for every level and every flat with its place written on it («الدور الثالث / شقة ٢»), its weight, and
 * the quantities an engineer fills in (columns, concrete, bricks, areas).
 *
 * The frame and the masonry go level by level; installations and finishing go flat by flat, each flat's trades one after the
 * other; every main stage waits for the one before it. All of it is an ordinary plan afterwards: the engineer edits, adds and
 * removes freely until the plan is agreed.
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
     * @param  array{floors:int,basement?:bool,apartments_per_floor:int}  $opts  `floors` counts the ground floor
     * @return int the number of works written
     */
    public function generate(Project $project, array $opts, ?Carbon $start = null): int
    {
        $this->order = 0;
        $this->made = 0;
        $this->previousPhase = null;

        $floors = max(1, min(30, (int) $opts['floors']));
        $flats = max(0, min(10, (int) $opts['apartments_per_floor']));

        $levels = [];
        if (! empty($opts['basement'])) {
            $levels[] = ['name' => 'البدروم', 'flats' => 0];
        }
        for ($i = 0; $i < $floors; $i++) {
            $levels[] = ['name' => self::ORDINALS[$i] ?? ('الدور ' . $i), 'flats' => $flats];
        }

        $first = ($start ?? ($project->starts_on ? Carbon::parse($project->starts_on) : Carbon::today()))->copy()->startOfDay();

        DB::transaction(function () use ($project, $levels, $first) {
            $day = $first->copy();

            // 1 — foundations
            $phase = $this->phase($project, 'الأساسات');
            $last = null;
            foreach ([['الحفر', 4, []], ['الخرسانة العادية', 3, [['الخرسانة', 'م³']]], ['القواعد والأساسات', 10, [['الخرسانة', 'م³'], ['حديد التسليح', 'طن'], ['عدد القواعد', 'قاعدة']]]] as [$title, $days, $items]) {
                [$last, $end] = $this->work($project, $phase, $title, 'المبنى', $days, $day, $items, $last);
                $day = $end->copy()->addDay();
            }

            // 2 — the concrete frame, level by level
            $phase = $this->phase($project, 'الهيكل الخرساني');
            $last = null;
            foreach ($levels as $level) {
                [$columns, $end] = $this->work($project, $phase, 'الأعمدة', $level['name'], 6, $day, [['عدد الأعمدة', 'عمود'], ['الخرسانة', 'م³'], ['حديد التسليح', 'طن']], $last);
                [$last, $end] = $this->work($project, $phase, 'السقف', $level['name'], 8, $end->copy()->addDay(), [['الخرسانة', 'م³'], ['حديد التسليح', 'طن']], $columns);
                $day = $end->copy()->addDay();
            }

            // 3 — masonry, level by level
            $phase = $this->phase($project, 'المباني');
            $last = null;
            foreach ($levels as $level) {
                [$last, $end] = $this->work($project, $phase, 'مباني الطوب', $level['name'], 7, $day, [['الطوب', 'طوبة'], ['الأسمنت', 'كيس']], $last);
                $day = $end->copy()->addDay();
            }

            // 4 — installations, flat by flat (electrical, then plumbing)
            $phase = $this->phase($project, 'التمديدات');
            $installEnd = $day->copy();
            foreach ($levels as $level) {
                for ($f = 1; $f <= $level['flats']; $f++) {
                    $zone = $level['name'] . ' / شقة ' . $f;
                    [$electrical, $end] = $this->work($project, $phase, 'الكهرباء', $zone, 5, $day, [['نقاط الكهرباء', 'نقطة']]);
                    [, $end] = $this->work($project, $phase, 'السباكة', $zone, 5, $end->copy()->addDay(), [['نقاط المياه والصرف', 'نقطة']], $electrical);
                    $installEnd = $end->gt($installEnd) ? $end->copy() : $installEnd;
                }
            }

            // 5 — finishing, flat by flat, the trades in their order
            $phase = $this->phase($project, 'التشطيبات');
            $day = $installEnd->copy()->addDay();
            $finishEnd = $day->copy();
            $trades = [['المحارة', 7, [['المساحة', 'م²']]], ['الجبس والأسقف', 4, [['المساحة', 'م²']]], ['السيراميك', 7, [['المساحة', 'م²']]], ['الرخام', 4, [['المساحة', 'م²']]], ['الأبواب والشبابيك', 4, [['عدد الأبواب والشبابيك', 'قطعة']]], ['النقاشة', 6, [['المساحة', 'م²']]], ['التكييف', 3, [['عدد الوحدات', 'وحدة']]]];

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

            // 6 — handover
            $phase = $this->phase($project, 'التسليم');
            $this->work($project, $phase, 'التنظيف والتسليم النهائي', 'المبنى', 3, $finishEnd->copy()->addDay());
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
