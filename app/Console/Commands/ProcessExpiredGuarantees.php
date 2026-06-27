<?php

namespace App\Console\Commands;

use App\Models\UserGuarantee;
use App\Services\Guarantees\GuaranteeExpirationService;
use Illuminate\Console\Command;

final class ProcessExpiredGuarantees extends Command
{
    protected $signature = 'guarantees:process-expired {--limit=200}';

    protected $description = 'Process expired guarantees.';

    public function handle(GuaranteeExpirationService $service): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $processed = 0;
        $changed = 0;
        $failed = 0;

        $items = UserGuarantee::query()
            ->whereIn('status', [
                UserGuarantee::STATUS_ACTIVE,
                UserGuarantee::STATUS_PENDING_OPERATIONS,
                UserGuarantee::STATUS_UNDERFUNDED,
            ])
            ->whereNotNull('meta')
            ->orderBy('id')
            ->limit($limit)
            ->get();

        foreach ($items as $guarantee) {
            $processed++;

            try {
                $result = $service->expireIfDue(
                    guarantee: $guarantee,
                    referenceType: 'scheduled_job',
                    referenceId: (int) $guarantee->id,
                    meta: ['source' => 'ProcessExpiredGuarantees']
                );

                if ($result['changed'] ?? false) {
                    $changed++;
                }
            } catch (\Throwable $e) {
                $failed++;
                report($e);
            }
        }

        $this->info('Expired guarantees processed=' . $processed . ', changed=' . $changed . ', failed=' . $failed);

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
