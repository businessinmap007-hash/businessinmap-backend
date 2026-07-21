<?php

namespace App\Services;

use App\Models\FraudFlag;
use Illuminate\Support\Facades\DB;

/**
 * Raises suspected-fraud flags from the operation-rating graph.
 *
 * The durable fraud signal is behaviour, not identity: an account whose
 * operations end disputed or cancelled far more often than normal. This scans
 * the per-user, per-role aggregates in `user_operation_ratings`, summed to the
 * account, and flags the ones over threshold FOR REVIEW. It never fines or
 * bans — that decision stays with an admin (the user's explicit choice). A flag
 * a human dismissed is left dismissed; the scan won't resurrect it.
 */
class FraudDetectionService
{
    /** @return array{scanned:int,flagged:int,cleared:int} */
    public function scan(int $limit = 500): array
    {
        $minOps = (int) config('bim.fraud.min_operations', 5);
        $disputedThreshold = (float) config('bim.fraud.disputed_ratio', 0.30);
        $cancelledThreshold = (float) config('bim.fraud.cancelled_ratio', 0.50);

        // Sum a user's rating rows across roles: the account is what gets fined
        // or banned, not one of its hats.
        $rows = DB::table('user_operation_ratings')
            ->select('user_id')
            ->selectRaw('SUM(total_operations) AS total_ops')
            ->selectRaw('SUM(disputed_count) AS disputed')
            ->selectRaw('SUM(cancelled_count) AS cancelled')
            ->groupBy('user_id')
            ->havingRaw('SUM(total_operations) >= ?', [$minOps])
            ->limit($limit)
            ->get();

        $scanned = 0;
        $flagged = 0;

        foreach ($rows as $row) {
            $scanned++;

            $total = (int) $row->total_ops;
            if ($total <= 0) {
                continue;
            }

            $disputedRatio = round((int) $row->disputed / $total, 4);
            $cancelledRatio = round((int) $row->cancelled / $total, 4);

            $reasons = [];
            if ($disputedRatio >= $disputedThreshold) {
                $reasons[] = 'disputed_ratio';
            }
            if ($cancelledRatio >= $cancelledThreshold) {
                $reasons[] = 'cancelled_ratio';
            }

            if (empty($reasons)) {
                continue;
            }

            // Weighted toward disputes — a dispute is a counterparty saying they
            // were wronged, a stronger signal than a self-cancel.
            $score = round(min(1, $disputedRatio * 0.7 + $cancelledRatio * 0.3), 4);

            $existing = FraudFlag::query()->where('user_id', (int) $row->user_id)->first();

            // Never reopen a flag a human already cleared.
            if ($existing && $existing->status === FraudFlag::STATUS_DISMISSED) {
                continue;
            }

            FraudFlag::updateOrCreate(
                ['user_id' => (int) $row->user_id],
                [
                    'score' => $score,
                    'total_operations' => $total,
                    'disputed_ratio' => $disputedRatio,
                    'cancelled_ratio' => $cancelledRatio,
                    'reasons' => $reasons,
                    'status' => FraudFlag::STATUS_OPEN,
                    'flagged_at' => now(),
                ]
            );

            $flagged++;
        }

        return ['scanned' => $scanned, 'flagged' => $flagged, 'cleared' => 0];
    }

    /** An admin marks a flag a false positive so it stops resurfacing. */
    public function dismiss(FraudFlag $flag, int $adminId): FraudFlag
    {
        $flag->update([
            'status' => FraudFlag::STATUS_DISMISSED,
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
        ]);

        return $flag->fresh();
    }
}
