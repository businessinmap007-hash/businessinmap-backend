<?php

namespace App\Console\Commands;

use App\Services\Clinics\ClinicAppointmentService;
use Illuminate\Console\Command;

class SendClinicAppointmentReminders extends Command
{
    protected $signature = 'clinic:appointment-reminders {--limit=200} {--within-hours=24}';

    protected $description = 'Send one-time pre-visit reminders for upcoming confirmed clinic appointments.';

    public function handle(ClinicAppointmentService $service): int
    {
        $sent = $service->sendDueReminders(
            max((int) $this->option('limit'), 1),
            max((int) $this->option('within-hours'), 1),
        );

        $this->info("Sent clinic appointment reminders: {$sent}");

        return self::SUCCESS;
    }
}
