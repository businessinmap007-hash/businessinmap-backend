<?php

namespace App\Console\Commands;

use App\Models\Medicine;
use Illuminate\Console\Command;

/**
 * The whole dictionary as one sheet, so a human can finish what a parser cannot.
 *
 * 42% of the register states no strength anywhere — not in a column, not in the
 * name — and no amount of pattern-matching invents one. Nor can a pattern tell
 * «AUGMENTIN 1 GM» from «A.ONE SOAP 100 GM». Both are jobs for someone who
 * knows what the product is.
 *
 * So the loop is: **export → edit in Excel → `medicines:import`**. The sheet
 * carries an `id` column and the importer reads it, which is what makes the
 * round trip lossless: a corrected strength lands on the row it was corrected
 * for, instead of colliding with (name, strength) and creating a twin.
 *
 * The file opens straight in Excel with Arabic intact — the BOM is deliberate,
 * and it is the same one the importer strips on the way back in.
 */
class ExportMedicines extends Command
{
    protected $signature = 'medicines:export
        {file? : Where to write (defaults to storage/app/medicines-<date>.csv)}
        {--missing-strength : Only the rows no strength could be found for}';

    protected $description = 'Export the medicine dictionary as a CSV for review';

    public function handle(): int
    {
        $path = (string) ($this->argument('file')
            ?: storage_path('app/medicines-' . now()->toDateString() . '.csv'));

        $handle = fopen($path, 'w');

        if (! $handle) {
            $this->error("Cannot write {$path}");

            return self::FAILURE;
        }

        // Excel reads a CSV as the local codepage unless this is here, and every
        // Arabic name arrives as mojibake. The importer strips it coming back.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, Medicine::SHEET_COLUMNS);

        $query = Medicine::query()
            ->when($this->option('missing-strength'), fn ($q) => $q
                ->whereNull('strength')->whereNull('strength_derived'))
            ->orderBy('name');

        $written = 0;
        $bar = $this->output->createProgressBar((clone $query)->count());

        foreach ($query->cursor() as $medicine) {
            fputcsv($handle, $medicine->toSheetRow());
            $written++;
            $bar->advance();
        }

        fclose($handle);
        $bar->finish();
        $this->newLine(2);

        $this->info("{$written} rows → {$path}");
        $this->line('Edit the strength column, then: php artisan medicines:import ' . basename($path));

        return self::SUCCESS;
    }
}
