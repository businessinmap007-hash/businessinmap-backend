<?php

namespace App\Console\Commands;

use App\Services\Agenda\AgendaService;
use Illuminate\Console\Command;

class SendAgendaReminders extends Command
{
    protected $signature = 'agenda:send-reminders {--limit=300}';

    protected $description = 'Push due agenda reminders (medication doses, personal tasks).';

    public function handle(AgendaService $agenda): int
    {
        $sent = $agenda->sendDueReminders(max((int) $this->option('limit'), 1));

        $this->info("Sent agenda reminders: {$sent}");

        return self::SUCCESS;
    }
}
