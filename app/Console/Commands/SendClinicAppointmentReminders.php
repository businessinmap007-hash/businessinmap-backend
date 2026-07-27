<?php

namespace App\Console\Commands;

use App\Services\Clinics\ClinicAppointmentService;
use Illuminate\Console\Command;

class SendClinicAppointmentReminders extends Command
{
    protected $signature = 'clinic:appointment-reminders {--limit=200}';

    protected $description = 'Send pre-visit reminders to patients (a day before and 2 hours before).';

    public function handle(ClinicAppointmentService $service): int
    {
        $sent = $service->sendDueReminders(max((int) $this->option('limit'), 1));

        $this->info("Sent clinic appointment reminders: {$sent}");

        return self::SUCCESS;
    }
}
