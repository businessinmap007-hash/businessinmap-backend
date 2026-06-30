<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogImportService;
use Illuminate\Console\Command;

class ImportCatalogData extends Command
{
    protected $signature = 'bim:catalog-import
        {section : Import section folder name, e.g. supermarket}
        {--base= : Optional base path. Default storage/app/catalog_import}
        {--dry-run : Validate files without writing to database}';

    protected $description = 'Import BIM product catalog files into the V3 catalog tables without duplicating existing data.';

    public function handle(CatalogImportService $service): int
    {
        $section = (string) $this->argument('section');
        $base = $this->option('base') ? (string) $this->option('base') : null;
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Starting BIM catalog import...');
        $this->line('Section: ' . $section);
        $this->line('Mode: ' . ($dryRun ? 'DRY RUN' : 'WRITE'));

        try {
            $result = $service->import($section, $base, $dryRun);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Import summary');

        foreach ($result['stats'] as $file => $stat) {
            if (($stat['skipped'] ?? false) === true) {
                $this->warn("- {$file}: skipped ({$stat['reason']})");
                continue;
            }

            $this->line("- {$file}: processed {$stat['processed']}, failed {$stat['failed']}");
        }

        if (! empty($result['errors'])) {
            $this->newLine();
            $this->error('Errors: ' . count($result['errors']));

            foreach (array_slice($result['errors'], 0, 20) as $error) {
                $this->line("{$error['file']}:{$error['line']} - {$error['message']}");
            }

            $this->warn('Only the first 20 errors are displayed. Fix the files and rerun the command.');
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Catalog import completed successfully.');

        return self::SUCCESS;
    }
}
