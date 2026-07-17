<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The day-31 sweep (BIM-15.1): accounts whose grace window has run out get their
 * balance escheated to the treasury and their identity scrubbed.
 *
 * This is the only irreversible step in deletion, so it is deliberately timid:
 * AccountDeletionService::finalize() refuses and flags for a human whenever the
 * money is locked or a dispute appeared after the request. An unattended job
 * must never be the thing that decides a contested balance.
 *
 * --dry-run prints what it would do and changes nothing.
 */
final class FinalizeAccountDeletions extends Command
{
    protected $signature = 'accounts:finalize-deletions {--limit=100} {--dry-run}';

    protected $description = 'Escheat the balance and anonymize accounts whose deletion grace window has expired.';

    public function handle(AccountDeletionService $deletion): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $dryRun = (bool) $this->option('dry-run');

        $due = $deletion->dueForFinalization($limit);

        if ($due->isEmpty()) {
            $this->info('No accounts are due for finalization.');

            return self::SUCCESS;
        }

        $finalized = 0;
        $held = 0;
        $failed = 0;
        $escheated = 0.0;

        foreach ($due as $user) {
            if ($dryRun) {
                $this->line(sprintf(
                    '  [dry-run] #%d — due %s',
                    $user->id,
                    $user->deletion_scheduled_at?->toDateString() ?? '?'
                ));

                continue;
            }

            try {
                $result = $deletion->finalize($user);

                if ($result['status'] === 'finalized') {
                    $finalized++;
                    $escheated += $result['escheated'];
                } elseif ($result['status'] === 'held') {
                    $held++;
                    $this->warn(sprintf('  #%d held: %s', $user->id, $result['reason'] ?? ''));
                }
            } catch (\Throwable $e) {
                $failed++;

                // One bad account must not stop the sweep.
                Log::error('Finalizing an account deletion failed.', [
                    'user_id' => (int) $user->id,
                    'error' => $e->getMessage(),
                ]);

                $this->error(sprintf('  #%d failed: %s', $user->id, $e->getMessage()));
            }
        }

        if ($dryRun) {
            $this->info(sprintf('%d account(s) would be finalized.', $due->count()));

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Finalized %d, held %d, failed %d. Escheated %s to the treasury.',
            $finalized,
            $held,
            $failed,
            number_format($escheated, 2)
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
