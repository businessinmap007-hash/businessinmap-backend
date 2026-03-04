<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class DetectUnusedControllers extends Command
{
    protected $signature = 'detect:unused-controllers';
    protected $description = 'Detect unused controllers by reading php artisan route:list output (no JSON needed)';

    public function handle(): int
    {
        // شغّل route:list وخد الناتج كنص
        $output = shell_exec(PHP_BINARY . ' artisan route:list 2>&1');

        if (!$output || !is_string($output)) {
            $this->error('Failed to run route:list.');
            return self::FAILURE;
        }

        // استخرج أي شيء على شكل: SomethingController@method
        preg_match_all('/([A-Za-z0-9_\\\\\/]+Controller)@/m', $output, $m);

        $usedBaseNames = [];
        foreach ($m[1] ?? [] as $controllerStr) {
            // قد يأتي كـ App\Http\Controllers\XController أو \App\Http\Controllers\XController
            $controllerStr = str_replace('/', '\\', $controllerStr);
            $usedBaseNames[] = class_basename(ltrim($controllerStr, '\\'));
        }

        $usedBaseNames = array_values(array_unique($usedBaseNames));
        sort($usedBaseNames);

        // كل الكنترولرز الموجودة فعليًا
        $all = collect(File::allFiles(app_path('Http/Controllers')))
            ->map(fn($f) => $f->getFilenameWithoutExtension())
            ->filter(fn($name) => $name !== 'Controller') // base controller
            ->values()
            ->toArray();

        $unused = array_values(array_diff($all, $usedBaseNames));
        sort($unused);

        File::put(
            storage_path('used_controllers.json'),
            json_encode($usedBaseNames, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        File::put(
            storage_path('unused_controllers.json'),
            json_encode($unused, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        $this->info('Used controllers: ' . count($usedBaseNames));
        $this->info('Unused controllers: ' . count($unused));
        $this->info('Saved: storage/used_controllers.json + storage/unused_controllers.json');

        return self::SUCCESS;
    }
}
