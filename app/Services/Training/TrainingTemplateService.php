<?php

namespace App\Services\Training;

use App\Models\TrainingPlan;
use App\Models\TrainingPlanTemplate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reusable training templates: a trainer builds one once, then applies it to a
 * client — which COPIES its exercises and meals into a fresh training plan (so
 * later edits to the template never affect a plan already handed out).
 */
class TrainingTemplateService
{
    public function __construct(private readonly TrainingPlanService $plans)
    {
    }

    /**
     * @param  array<int,array<string,mixed>>  $exercises
     * @param  array<int,array<string,mixed>>  $meals
     */
    public function create(User $trainer, array $header, array $exercises, array $meals): TrainingPlanTemplate
    {
        return DB::transaction(function () use ($trainer, $header, $exercises, $meals) {
            $template = TrainingPlanTemplate::create([
                'trainer_id' => (int) $trainer->id,
                'title' => $header['title'],
                'goal' => $header['goal'] ?? null,
                'notes' => $header['notes'] ?? null,
            ]);

            foreach ($exercises as $e) {
                $template->exercises()->create([
                    'day_of_week' => $e['day_of_week'] ?? null,
                    'name' => $e['name'],
                    'sets' => $e['sets'] ?? null,
                    'reps' => $e['reps'] ?? null,
                    'rest_seconds' => $e['rest_seconds'] ?? null,
                    'notes' => $e['notes'] ?? null,
                    'sort_order' => $e['sort_order'] ?? 0,
                ]);
            }

            foreach ($meals as $m) {
                $template->meals()->create([
                    'meal_type' => $m['meal_type'],
                    'name' => $m['name'],
                    'calories' => $m['calories'] ?? null,
                    'notes' => $m['notes'] ?? null,
                    'sort_order' => $m['sort_order'] ?? 0,
                ]);
            }

            return $template->load(['exercises', 'meals']);
        });
    }

    /**
     * Apply a template to a client: build a fresh plan from a COPY of the
     * template's items. Header fields (title/goal/dates/notes) may be overridden;
     * otherwise the template's own title/goal/notes are used.
     */
    public function apply(TrainingPlanTemplate $template, User $trainer, User $client, array $overrides): TrainingPlan
    {
        $template->loadMissing(['exercises', 'meals']);

        $exercises = $template->exercises->map(fn ($e) => [
            'day_of_week' => $e->day_of_week,
            'name' => $e->name,
            'sets' => $e->sets,
            'reps' => $e->reps,
            'rest_seconds' => $e->rest_seconds,
            'notes' => $e->notes,
            'sort_order' => $e->sort_order,
        ])->all();

        $meals = $template->meals->map(fn ($m) => [
            'meal_type' => $m->meal_type,
            'name' => $m->name,
            'calories' => $m->calories,
            'notes' => $m->notes,
            'sort_order' => $m->sort_order,
        ])->all();

        return $this->plans->create($trainer, $client, [
            'title' => $overrides['title'] ?? $template->title,
            'goal' => $overrides['goal'] ?? $template->goal,
            'starts_on' => $overrides['starts_on'] ?? null,
            'ends_on' => $overrides['ends_on'] ?? null,
            'notes' => $overrides['notes'] ?? $template->notes,
        ], $exercises, $meals);
    }
}
