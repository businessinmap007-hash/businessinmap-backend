<?php

namespace App\Console\Commands;

use App\Services\Investigations\InvestigationOrderService;
use Illuminate\Console\Command;

/**
 * Deletes the result PHOTOS of investigation orders that are safe on the patient\'s phone (and read by the ordering
 * doctor), and of those nobody kept past the retention window — warning the patient before. Text results stay.
 */
class PurgeInvestigationResultFiles extends Command
{
    protected $signature = 'investigations:purge-result-files';

    protected $description = 'Delete result photos that the patient has kept (and the doctor has read), or that expired unkept.';

    public function handle(InvestigationOrderService $service): int
    {
        $r = $service->purgeFiles();
        $this->info("Investigation result photos: {$r['purged']} orders purged, {$r['warned']} patients warned.");

        return self::SUCCESS;
    }
}
