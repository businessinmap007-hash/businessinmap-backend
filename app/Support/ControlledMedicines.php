<?php

namespace App\Support;

use App\Models\Medicine;

/**
 * «هناك بعض الأدوية المخدرة فلا بد من صورة روشتة بخط الطبيب منعًا للاحتيال» — المالك، 2026-10-05.
 *
 * Which dictionary drugs are CONTROLLED (narcotic / psychotropic): a prescription that contains one cannot be
 * issued without a photo of the paper prescription in the doctor's own handwriting, and a pharmacy cannot dispense
 * it without that photo.
 *
 * The list is by ACTIVE INGREDIENT and matched as whole words, so «ipratropium» is not «opium» and «apomorphine» is
 * not «morphine». It is deliberately a starting list — what is controlled is a regulator's decision, not ours: a
 * pharmacist should review it. Widely prescribed drugs the register may treat differently (pregabalin, gabapentin,
 * zopiclone, modafinil, ephedrine, dextromethorphan) are NOT on it. Edit {@see SUBSTANCES} and run
 * `php artisan medicines:flag-controlled` to apply a change.
 */
final class ControlledMedicines
{
    public const SUBSTANCES = [
        // opioids
        'morphine', 'codeine', 'dihydrocodeine', 'tramadol', 'pethidine', 'meperidine', 'fentanyl', 'oxycodone', 'hydrocodone',
        'buprenorphine', 'methadone', 'tapentadol', 'nalbuphine', 'pentazocine', 'dextropropoxyphene', 'opium',
        // benzodiazepines and sleeping pills
        'diazepam', 'alprazolam', 'clonazepam', 'bromazepam', 'midazolam', 'lorazepam', 'nitrazepam', 'flunitrazepam',
        'oxazepam', 'chlordiazepoxide', 'clorazepate', 'clobazam', 'temazepam', 'estazolam', 'zolpidem',
        // barbiturates
        'phenobarbital', 'phenobarbitone', 'pentobarbital', 'secobarbital',
        // stimulants and others
        'methylphenidate', 'amphetamine', 'dexamphetamine', 'ketamine',
    ];

    /** Does this drug (its ingredient, or failing that its name) contain a controlled substance? */
    public static function matches(?string $scientificName, ?string $name = null): bool
    {
        foreach ([$scientificName, $name] as $text) {
            if ($text !== null && $text !== '' && preg_match(self::pattern(), $text) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Flag every dictionary row that matches (and unflag one that no longer does). Returns how many rows are
     * flagged after the run; with `$dryRun` nothing is written.
     */
    public static function flagAll(bool $dryRun = false): int
    {
        $candidates = Medicine::query()->where(function ($q) {
            foreach (self::SUBSTANCES as $substance) {
                $q->orWhere('scientific_name', 'like', "%{$substance}%")->orWhere('name', 'like', "%{$substance}%");
            }
        })->get(['id', 'name', 'scientific_name', 'is_controlled']);

        $flag = $candidates->filter(fn (Medicine $m) => self::matches($m->scientific_name, $m->name))->pluck('id')->all();
        $stale = Medicine::query()->where('is_controlled', true)->whereNotIn('id', $flag)->pluck('id')->all();

        if (! $dryRun) {
            foreach (array_chunk($flag, 500) as $ids) {
                Medicine::query()->whereIn('id', $ids)->update(['is_controlled' => true]);
            }
            foreach (array_chunk($stale, 500) as $ids) {
                Medicine::query()->whereIn('id', $ids)->update(['is_controlled' => false]);
            }
        }

        return count($flag);
    }

    private static function pattern(): string
    {
        return '/\b(' . implode('|', array_map('preg_quote', self::SUBSTANCES)) . ')\b/i';
    }
}
