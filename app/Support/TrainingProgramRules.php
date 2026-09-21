<?php

namespace App\Support;

/**
 * The programme fields an exercise can carry (per-set weights, how they climb,
 * what the day is called), validated and normalised in ONE place — a plan, a
 * template, and both «add exercise» endpoints all take them.
 */
final class TrainingProgramRules
{
    /**
     * Rules for the programme fields. $prefix is «» for a single exercise and
     * «exercises.*.» for an inline list.
     *
     * @return array<string,array<int,mixed>>
     */
    public static function exercise(string $prefix = ''): array
    {
        return [
            $prefix . 'day_label' => ['nullable', 'string', 'max:40'],
            $prefix . 'set_weights' => ['nullable', 'array', 'max:12'],
            $prefix . 'set_weights.*' => ['numeric', 'min:0', 'max:1000'],
            $prefix . 'progress_every_weeks' => ['nullable', 'integer', 'min:1', 'max:26'],
            $prefix . 'progress_increment_kg' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Programme-level fields: how long it runs and the default weight progression
     * for exercises that do not carry their own.
     *
     * @return array<string,array<int,mixed>>
     */
    public static function header(): array
    {
        return [
            'duration_weeks' => ['nullable', 'integer', 'min:1', 'max:' . ExerciseProgression::MAX_WEEKS],
            'progression' => ['nullable', 'array'],
            'progression.every_weeks' => ['nullable', 'integer', 'min:1', 'max:26'],
            'progression.increment_kg' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * The attributes to store for one exercise, with the programme-level default
     * progression filled in where the exercise has weights but no rule of its own.
     *
     * @param  array<string,mixed>       $e
     * @param  array<string,mixed>|null  $defaults  ['every_weeks' => int, 'increment_kg' => float]
     * @return array<string,mixed>
     */
    public static function attributes(array $e, ?array $defaults = null): array
    {
        $setWeights = isset($e['set_weights']) && is_array($e['set_weights']) && $e['set_weights'] !== []
            ? array_values(array_map('floatval', $e['set_weights']))
            : null;

        $hasWeight = $setWeights !== null || (isset($e['target_weight']) && $e['target_weight'] !== null);

        $every = $e['progress_every_weeks'] ?? ($hasWeight ? ($defaults['every_weeks'] ?? null) : null);
        $increment = $e['progress_increment_kg'] ?? ($hasWeight ? ($defaults['increment_kg'] ?? null) : null);

        return [
            'day_of_week' => $e['day_of_week'] ?? null,
            'day_label' => isset($e['day_label']) && trim((string) $e['day_label']) !== '' ? trim((string) $e['day_label']) : null,
            'name' => $e['name'],
            'sets' => $e['sets'] ?? null,
            'reps' => $e['reps'] ?? null,
            'target_weight' => $e['target_weight'] ?? null,
            'set_weights' => $setWeights,
            'progress_every_weeks' => $every,
            'progress_increment_kg' => $increment,
            'rest_seconds' => $e['rest_seconds'] ?? null,
            'notes' => $e['notes'] ?? null,
            'sort_order' => $e['sort_order'] ?? 0,
        ];
    }
}
