<?php

namespace App\Console\Commands;

use App\Services\DisputeService;
use App\Services\DisputeWarningService;
use Illuminate\Console\Command;

/**
 * Makes time a party to a dispute.
 *
 * Both halves of this already existed and neither ran: DisputeWarningService
 * chased the parties correctly but nothing ever called it, and the
 * mutual-resolution deadline was written on every dispute and read by no code.
 * So a dispute opened, sat, and stayed open — which is why an arbitration step
 * had nothing to trigger it.
 */
final class ProcessDisputes extends Command
{
    protected $signature = 'disputes:process {--limit=100}';

    protected $description = 'Send due dispute warnings and escalate expired mutual-resolution windows.';

    public function handle(DisputeWarningService $warnings, DisputeService $disputes): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $failed = 0;

        $sent = 0;
        try {
            $sent = $warnings->sendDueWarnings($limit)->count();
        } catch (\Throwable $e) {
            $failed++;
            report($e);
        }

        // Escalation runs even if the warnings failed: a party who was never
        // chased still should not be trapped in an expired window forever.
        $escalated = [];
        try {
            $escalated = $disputes->escalateExpired($limit);
        } catch (\Throwable $e) {
            $failed++;
            report($e);
        }

        $this->info('Disputes processed warned=' . $sent . ', escalated=' . count($escalated) . ', failed=' . $failed);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
