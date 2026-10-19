<?php

namespace App\Console\Commands;

use App\Services\Training\TrainingPlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AlertExpiringTrainingPlans extends Command
{
    protected $signature = 'training:alert-expiring {--date= : Evaluate as of this date (Y-m-d); default today}';

    protected $description = "Tell a trainer and a client, once per month, that a plan's paid month is about to run out.";

    public function handle(TrainingPlanService $plans): int
    {
        $asOf = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();

        $this->info('Plans announced: ' . $plans->alertExpiring($asOf));

        return self::SUCCESS;
    }
}
