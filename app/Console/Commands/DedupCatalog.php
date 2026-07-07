<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogDedupService;
use Illuminate\Console\Command;

class DedupCatalog extends Command
{
    protected $signature = 'bim:catalog-dedup {--dry-run : Measure duplicates and backfill dedup keys without soft-deleting}';

    protected $description = 'De-duplicate catalog products by barcode then normalized name: keep one master per group, link + soft-delete the rest, and backfill dedup_key.';

    public function handle(CatalogDedupService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Catalog dedup — ' . ($dryRun ? 'DRY RUN (measure + backfill keys)' : 'APPLY'));

        $r = $service->runBatchDedup($dryRun);

        $this->table(
            ['active', 'masters (kept)', 'duplicates', 'applied'],
            [[$r['active'], $r['masters'], $r['duplicates'], $r['applied'] ? 'yes' : 'no']]
        );

        if ($dryRun && $r['duplicates'] > 0) {
            $this->warn("Run without --dry-run to soft-delete {$r['duplicates']} duplicates.");
        }

        return self::SUCCESS;
    }
}
