<?php

namespace App\Console\Commands;

use App\Services\Retail\RetailCatalogNudgeService;
use Illuminate\Console\Command;

/**
 * One-time-per-business nudge: an eligible retail merchant who has never
 * listed a single product is told the shared catalog now has real stock
 * under their allowed types. See RetailCatalogNudgeService's docblock.
 */
final class NotifyRetailCatalogReady extends Command
{
    protected $signature = 'retail:notify-catalog-ready {--limit=200}';

    protected $description = 'Notify retail-eligible businesses, once each, that the shared catalog has stock for them.';

    public function handle(RetailCatalogNudgeService $nudger): int
    {
        $r = $nudger->run(max((int) $this->option('limit'), 1));

        $this->info("Retail catalog nudge: scanned={$r['scanned']}, notified={$r['notified']}.");

        return self::SUCCESS;
    }
}
