<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DetectUnusedModels extends Command
{
    protected $signature = 'detect:unused-models';
    protected $description = 'Detect unused models by scanning controllers';

    public function handle()
    {
        $modelPath = app_path('Models');
        $controllerPath = app_path('Http/Controllers');

        $models = collect(File::files($modelPath))
            ->map(fn($f) => $f->getFilenameWithoutExtension())
            ->toArray();

        $usedModels = [];

        foreach (File::allFiles($controllerPath) as $file) {
            $content = file_get_contents($file->getRealPath());

            foreach ($models as $model) {
                if (str_contains($content, 'use App\Models\\' . $model)
                    || str_contains($content, $model . '::')
                ) {
                    $usedModels[] = $model;
                }
            }
        }

        $usedModels = array_unique($usedModels);
        $unusedModels = array_values(array_diff($models, $usedModels));

        File::put(
            storage_path('unused_models.json'),
            json_encode($unusedModels, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $this->info('Unused models detected: ' . count($unusedModels));
    }
}
