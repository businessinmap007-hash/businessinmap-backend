<?php

namespace App\Console\Commands;

use App\Services\BookingReminderService;
use Illuminate\Console\Command;

class SendDueBookingReminders extends Command
{
    protected $signature = 'bookings:send-due-reminders {--limit=100}';

    protected $description = 'Send due booking reminders for clients and businesses.';

    public function handle(BookingReminderService $bookingReminderService): int
    {
        $limit = max((int) $this->option('limit'), 1);

        $sent = $bookingReminderService->sendDue($limit);

        $this->info("Sent booking reminders: {$sent}");

        return self::SUCCESS;
    }
}