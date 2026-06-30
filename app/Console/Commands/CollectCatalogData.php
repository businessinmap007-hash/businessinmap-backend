<?php

namespace App\Console\Commands;

use App\Services\Catalog\CatalogDataCollectorService;
use Illuminate\Console\Command;

class CollectCatalogData extends Command
{
    protected $signature = 'bim:catalog-collect
        {section : Section folder name, e.g. supermarket}
        {--base= : Optional base path. Default storage/app/catalog_data}';

    protected $description = 'Clean raw catalog data and export CSV files compatible with bim:catalog-import.';

    public function handle(CatalogDataCollectorService $service): int
    {
        $section = (string) $this->argument('section');
        $base = $this->option('base') ? (string) $this->option('base') : null;

        $this->info('Starting BIM catalog data collection...');
        $this->line('Section: ' . $section);

        try {
            $result = $service->collect($section, $base);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Collector summary');
        foreach ($result['stats'] as $key => $value) {
            $this->line('- ' . $key . ': ' . $value);
        }

        $this->newLine();
        $this->line('Export path: ' . $result['export_path']);

        if (! empty($result['errors'])) {
            $this->newLine();
            $this->error('Errors: ' . count($result['errors']));
            foreach (array_slice($result['errors'], 0, 20) as $error) {
                $this->line("{$error['file']}:{$error['line']} - {$error['message']}");
            }
            return self::FAILURE;
        }

        $this->info('Catalog data collection completed successfully.');
        return self::SUCCESS;
    }
}
