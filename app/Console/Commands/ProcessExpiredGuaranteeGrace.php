<?php

namespace App\Console\Commands;

use App\Models\UserGuarantee;
use App\Services\Guarantees\GuaranteeAutoDowngradeService;
use Illuminate\Console\Command;

final class ProcessExpiredGuaranteeGrace extends Command
{
    protected $signature = 'guarantees:process-expired-grace {--limit=200}';

    protected $description = 'Process expired guarantee grace periods and downgrade or suspend underfunded guarantees.';

    public function handle(GuaranteeAutoDowngradeService $guaranteeAutoDowngradeService): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $processed = 0;
        $changed = 0;
        $failed = 0;

        UserGuarantee::query()
            ->where('status', UserGuarantee::STATUS_UNDERFUNDED)
            ->whereNotNull('grace_until')
            ->where('grace_until', '<=', now())
            ->orderBy('grace_until')
            ->limit($limit)
            ->get()
            ->each(function (UserGuarantee $guarantee) use ($guaranteeAutoDowngradeService, &$processed, &$changed, &$failed) {
                $processed++;

                try {
                    $result = $guaranteeAutoDowngradeService->downgradeExpiredGrace(
                        guarantee: $guarantee,
                        referenceType: 'scheduled_job',
                        referenceId: (int) $guarantee->id,
                        meta: [
                            'source' => 'ProcessExpiredGuaranteeGrace',
                        ]
                    );

                    if ($result['changed'] ?? false) {
                        $changed++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    report($e);
                    $this->error('Failed guarantee #' . $guarantee->id . ': ' . $e->getMessage());
                }
            });

        $this->info("Expired guarantee grace processed={$processed}, changed={$changed}, failed={$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
