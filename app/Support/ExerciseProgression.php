<?php

namespace App\Support;

use App\Models\PlanExercise;
use App\Models\TemplateExercise;
use App\Models\TrainingPlan;
use Illuminate\Support\Carbon;

/**
 * Week numbers and progressive-overload targets for a programme.
 *
 * A week's target for an exercise is computed, never stored per week:
 *
 *   per-set base weights  =  set_weights (e.g. [20, 25, 30]),
 *                            else the flat target_weight repeated for each set
 *   step                  =  floor((week - 1) / progress_every_weeks)
 *   target                =  base + step * progress_increment_kg
 *
 * «20-25-30 for the first two weeks, then +5 kg» is set_weights [20,25,30],
 * every 2 weeks, +5 → weeks 1-2: 20/25/30, weeks 3-4: 25/30/35, weeks 5-6: 30/35/40.
 * The trainer edits the rule once and every later week follows.
 */
final class ExerciseProgression
{
    /** Longest programme we plan (a year). */
    public const MAX_WEEKS = 52;

    /** The plan's first day: its start date, else the day it was created. */
    public static function startOf(TrainingPlan $plan): Carbon
    {
        return ($plan->starts_on ?? $plan->created_at ?? now())->copy()->startOfDay();
    }

    /** Programme week (1-based) for a date; before the start it is week 1. */
    public static function weekNumber(TrainingPlan $plan, Carbon $date): int
    {
        $days = self::startOf($plan)->diffInDays($date->copy()->startOfDay(), false);

        return max(1, intdiv((int) $days, 7) + 1);
    }

    /** How many weeks the plan runs, or null when open-ended. */
    public static function totalWeeks(TrainingPlan $plan): ?int
    {
        if ($plan->duration_weeks) {
            return (int) $plan->duration_weeks;
        }

        if ($plan->starts_on && $plan->ends_on) {
            return max(1, (int) ceil(($plan->starts_on->diffInDays($plan->ends_on) + 1) / 7));
        }

        return null;
    }

    /**
     * The per-set weights for one programme week; empty when the exercise has no
     * weight at all (bodyweight work).
     *
     * @param  PlanExercise|TemplateExercise  $exercise
     * @return list<float>
     */
    public static function targetsFor($exercise, int $week): array
    {
        $base = self::baseWeights($exercise);

        if ($base === []) {
            return [];
        }

        $every = (int) ($exercise->progress_every_weeks ?? 0);
        $step = $every > 0 ? intdiv(max(1, $week) - 1, $every) : 0;
        $add = $step * (float) ($exercise->progress_increment_kg ?? 0);

        return array_map(fn (float $w) => round($w + $add, 2), $base);
    }

    /**
     * Every week of the programme, for the trainer's preview.
     *
     * @param  PlanExercise|TemplateExercise  $exercise
     * @return list<array{week:int,weights:list<float>}>
     */
    public static function schedule($exercise, int $weeks): array
    {
        $out = [];

        for ($w = 1; $w <= min($weeks, self::MAX_WEEKS); $w++) {
            $out[] = ['week' => $w, 'weights' => self::targetsFor($exercise, $w)];
        }

        return $out;
    }

    /**
     * Read a weights string the way a trainer writes it — «20-25-30», «20,25,30»,
     * «20 25 30» or «42.5» — into a list of numbers. A comma or a dash separates
     * weights; only a dot is a decimal point, so «20,25,30» is three weights,
     * never 20.25 and 30.
     *
     * @return list<float>
     */
    public static function parseWeights(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        preg_match_all('/\d+(?:\.\d+)?/', $text, $m);

        return array_values(array_map('floatval', $m[0]));
    }

    /** @return list<float> */
    private static function baseWeights($exercise): array
    {
        $set = $exercise->set_weights;

        if (is_array($set) && $set !== []) {
            $weights = array_map('floatval', array_values($set));
            $sets = (int) ($exercise->sets ?? 0);

            // Fewer weights than sets: the last one carries on (a ramp that tops out).
            while (count($weights) < $sets) {
                $weights[] = end($weights);
            }

            return $weights;
        }

        if ($exercise->target_weight !== null) {
            return array_fill(0, max(1, (int) ($exercise->sets ?? 1)), (float) $exercise->target_weight);
        }

        return [];
    }
}
