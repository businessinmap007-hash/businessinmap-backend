<?php

namespace App\Console\Commands;

use App\Services\FineService;
use Illuminate\Console\Command;

/**
 * Makes time collect a fine.
 *
 * A fine is frozen at levy but must not be captured until its appeal window
 * closes, so something has to notice that it closed — and, for a user who was
 * broke when fined, top up the frozen hold from any balance that has since
 * arrived before capturing.
 */
final class ProcessFines extends Command
{
    protected $signature = 'fines:process {--limit=100}';

    protected $description = 'Top up under-frozen fine holds and collect fines whose appeal window has closed.';

    public function handle(FineService $fines): int
    {
        $limit = max((int) $this->option('limit'), 1);

        try {
            $r = $fines->processDue($limit);
        } catch (\Throwable $e) {
            report($e);
            $this->error('Fine processing failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(
            'Fines processed topped_up=' . $r['topped_up']
            . ', collected=' . $r['collected']
            . ', still pending=' . $r['pending']
        );

        return self::SUCCESS;
    }
}
