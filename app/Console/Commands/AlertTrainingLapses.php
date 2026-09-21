<?php

namespace App\Console\Commands;

use App\Services\Training\TrainingPerformanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AlertTrainingLapses extends Command
{
    protected $signature = 'training:alert-lapses {--date= : Evaluate as of this date (Y-m-d); default today}';

    protected $description = 'Tell a trainer when a client has missed their scheduled training days (once per lapse).';

    public function handle(TrainingPerformanceService $performance): int
    {
        $asOf = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        $alerted = $performance->alertLapses($asOf);

        $this->info("Trainers alerted about a lapse: {$alerted}");

        return self::SUCCESS;
    }
}
