<?php

namespace App\Console\Commands;

use App\Support\ControlledMedicines;
use Illuminate\Console\Command;

/**
 * Re-applies {@see ControlledMedicines::SUBSTANCES} to the dictionary — run it after editing that list.
 */
class FlagControlledMedicines extends Command
{
    protected $signature = 'medicines:flag-controlled {--dry-run : count, change nothing}';

    protected $description = 'Mark the dictionary drugs that contain a controlled substance (they need a handwritten prescription photo).';

    public function handle(): int
    {
        $count = ControlledMedicines::flagAll((bool) $this->option('dry-run'));

        $this->info(($this->option('dry-run') ? 'Would flag ' : 'Flagged ') . $count . ' medicine(s) as controlled.');

        return self::SUCCESS;
    }
}
