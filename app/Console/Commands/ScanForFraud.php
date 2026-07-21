<?php

namespace App\Console\Commands;

use App\Services\FraudDetectionService;
use Illuminate\Console\Command;

/**
 * Raises suspected-fraud flags from the rating graph for admin review. Advisory
 * only — it never fines or bans.
 */
final class ScanForFraud extends Command
{
    protected $signature = 'fraud:scan {--limit=500}';

    protected $description = 'Flag accounts with an abnormal share of disputed/cancelled operations for review.';

    public function handle(FraudDetectionService $detector): int
    {
        try {
            $r = $detector->scan(max((int) $this->option('limit'), 1));
        } catch (\Throwable $e) {
            report($e);
            $this->error('Fraud scan failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info('Fraud scan scanned=' . $r['scanned'] . ', flagged=' . $r['flagged']);

        return self::SUCCESS;
    }
}
