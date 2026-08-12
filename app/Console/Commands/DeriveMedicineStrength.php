<?php

namespace App\Console\Commands;

use App\Models\Medicine;
use Illuminate\Console\Command;

/**
 * Read the strength back out of the drug's own name.
 *
 * The register we loaded has no strength column — it writes the dose into the
 * name («AUGMENTIN 1 GM 14 F.C.TABS.», «BRUFEN 100MG/5ML SYRUP 150ML»), so for
 * about 58% of rows it can be recovered without inventing anything. The result
 * lands in `strength_derived`, never in `strength`: a parsed value is not a
 * stated one, and `strength` is half the row's identity.
 *
 * ## What is deliberately NOT read as a strength
 *
 * - **ML on its own.** «SUSP. 120 ML» is how big the bottle is. It counts only
 *   as the denominator of a ratio: «100MG/5ML». A first pass that allowed it
 *   claimed 82% coverage, a quarter of which was bottle sizes.
 * - **A bare number before a dosage form.** «20 F.C.TABS.» is a pack count.
 *
 * ## What is still a guess
 *
 * «A.ONE SOAP 100 GM» is how heavy the bar is, and no pattern separates that
 * from «AUGMENTIN 1 GM» without knowing what the product is. That is exactly
 * why the value is flagged derived and kept out of `strength` — a human
 * reviewing the exported sheet is the correction step, and
 * `medicines:export` → edit → `medicines:import` is the loop that carries it
 * back.
 */
class DeriveMedicineStrength extends Command
{
    protected $signature = 'medicines:derive-strength
        {--dry-run : Report coverage without writing}
        {--force : Also re-derive rows that already carry a derived value}';

    protected $description = 'Recover each drug strength from its name into strength_derived';

    /**
     * A number joined to a real strength unit, optionally as a ratio.
     *
     * ML, DOSE and L appear only after the slash, where they are the volume the
     * dose sits in rather than the dose.
     */
    private const PATTERN = '~(\d+(?:[.,]\d+)?)\s*(MG|MCG|UG|GM|G|IU|%)\b(?:\s*/\s*(\d+(?:[.,]\d+)?)?\s*(ML|MG|G|DOSE|L)\b)?~i';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $query = Medicine::query()
            ->when(! $this->option('force'), fn ($q) => $q->whereNull('strength_derived'));

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to derive.');

            return self::SUCCESS;
        }

        $found = 0;
        $blank = 0;
        $shown = 0;
        $bar = $dry ? null : $this->output->createProgressBar($total);

        foreach ($query->cursor() as $medicine) {
            $strength = self::read((string) $medicine->name);

            if ($strength === null) {
                $blank++;
            } else {
                $found++;

                if ($dry && $shown < 10) {
                    $this->line(sprintf('  %-48s → %s', mb_substr($medicine->name, 0, 46), $strength));
                    $shown++;
                }

                if (! $dry) {
                    $medicine->forceFill([
                        'strength_derived' => $strength,
                        'strength_is_derived' => true,
                    ])->saveQuietly();
                }
            }

            $bar?->advance();
        }

        $bar?->finish();
        $this->newLine($dry ? 1 : 2);

        $this->info(sprintf(
            '%s %d of %d (%.1f%%) · %d carry no stated strength',
            $dry ? 'Would derive' : 'Derived',
            $found,
            $total,
            100 * $found / $total,
            $blank
        ));

        return self::SUCCESS;
    }

    /** The strength inside a name, or null when the name states none. */
    public static function read(string $name): ?string
    {
        if (! preg_match_all(self::PATTERN, $name, $matches, PREG_SET_ORDER)) {
            return null;
        }

        $parts = [];

        foreach ($matches as $match) {
            $parts[] = preg_replace('/\s+/', ' ', trim($match[0]));
        }

        // «500MG+125MG» is one strength written in two halves, and both belong.
        $value = implode(' + ', array_unique($parts));

        return mb_strlen($value) > 120 ? mb_substr($value, 0, 120) : $value;
    }
}
